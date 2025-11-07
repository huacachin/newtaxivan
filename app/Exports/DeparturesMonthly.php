<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeparturesMonthly implements FromArray, WithHeadings, WithStyles, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected int $year,
        protected int $month,
        protected string $dateColumn = 'date',
        protected ?string $countColumn = null
    ) {}

    protected int   $daysInMonth = 0;
    protected array $days = [];
    protected array $rows = [];                 // [ vid => ['plate'=>..., 'daily'=>[1..n], 'total'=>int] ]
    protected array $totalPerDay = [];          // suma por día
    protected array $vehiclesWorkedPerDay = []; // # vehículos con salida por día

    /* ----------------- Headings / Data ----------------- */

    public function headings(): array
    {
        $this->prepare();
        $head = ['Item', 'Placa'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $head[] = (string)$d;
        $head[] = 'T. Salida';
        return $head;
    }

    public function array(): array
    {
        $this->prepare();

        $data = [];
        $i = 0;
        foreach ($this->rows as $r) {
            $i++;
            $row = [$i, $r['plate']];
            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                // SIEMPRE número para que muestre 0
                $row[] = (int)($r['daily'][$d] ?? 0);
            }
            $row[] = (int)$r['total'];
            $data[] = $row;
        }

        // Totales: “Total Salidas”
        $rowA = ['', 'Total Salidas'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $rowA[] = (int)($this->totalPerDay[$d] ?? 0);
        $rowA[] = array_sum($this->totalPerDay);
        $data[] = $rowA;

        // Totales: “Total V.T. (vehículos con salida)”
        $rowB = ['', 'Total V.T. (vehículos con salida)'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $rowB[] = (int)($this->vehiclesWorkedPerDay[$d] ?? 0);
        $rowB[] = array_sum($this->vehiclesWorkedPerDay);
        $data[] = $rowB;

        return $data;
    }

    /* ----------------- Styles básicos ----------------- */

    public function styles(Worksheet $sheet) { return []; }

    /* ----------------- AfterSheet: diseño ----------------- */

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($e) {
                $sheet = $e->sheet->getDelegate();

                // Paleta
                $blueDark   = 'FF2874A6'; // encabezado/título
                $footerFill = 'FFCEE7FF'; // pies
                $white  = 'FFFFFFFF';
                $fontBlack  = 'FF000000';
                $borderSoft = 'FFCFD8DC';
                $fontRed    = 'FFCC0000'; // domingos

                // Fuente global compacta
                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Título (fila 1)
                $sheet->insertNewRowBefore(1, 1);
                $lastCol = $sheet->getHighestColumn();
                $lastRow = (int)$sheet->getHighestRow();

                $title = 'REPORTE MENSUAL POR PLACA – V.T ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', $title);
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['argb'=>$white]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontRed], 'size'=>10],
                    'alignment' => ['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(18);

                // Cabecera (fila 2)
                $headerRow    = 2; // headings()
                $dataStartRow = 3; // datos
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$white], 'size'=>10],
                    'alignment' => ['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(18);

                // Domingos en rojo (solo header de días)
                $firstDayColIdx = 3; // C = día 1
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $date = \Carbon\Carbon::create($this->year, $this->month, $d);
                    if ($date->isSunday()) {
                        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstDayColIdx + ($d - 1));
                        $sheet->getStyle("{$col}{$headerRow}")->getFont()->getColor()->setARGB($fontRed);
                    }
                }

                // Congelar encabezado y fijar Item/Placa
                $sheet->freezePane('C3');

                // === Quitar cualquier AutoFilter residual (A/B u otros) ===
                if (method_exists($sheet, 'setAutoFilter')) {
                    $sheet->setAutoFilter(null);
                }
                if (method_exists($sheet, 'getAutoFilter')) {
                    try { $sheet->getAutoFilter()->setRange(''); } catch (\Throwable $e2) {}
                }

                // Grilla
                $lastColIdx   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
                $totalColIdx  = $lastColIdx; // última = T. Salida
                $dataRows     = count($this->rows);
                $dataEndRow   = $dataRows > 0 ? ($dataStartRow + $dataRows - 1) : ($dataStartRow - 1);

                // Bordes finos
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderSoft);

                // Evitar wrap/indent que agregan aire
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")
                    ->getAlignment()->setWrapText(false)->setIndent(0);

                // ---- ANCHOS compactos (desactivar autosize para que respeten) ----
                foreach (['A','B'] as $colLetter) {
                    $sheet->getColumnDimension($colLetter)->setAutoSize(false);
                }
                $sheet->getColumnDimension('A')->setWidth(6.0);   // Item
                $sheet->getColumnDimension('B')->setWidth(9.5);   // Placa

                for ($c = $firstDayColIdx; $c < $totalColIdx; $c++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth(3.0); // días
                }
                $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColIdx);
                $sheet->getColumnDimension($totalColLetter)->setAutoSize(false);
                $sheet->getColumnDimension($totalColLetter)->setWidth(6.5); // T. Salida

                // ---- Forzar 0 en celdas vacías (días y total) + formato entero ----
                if ($dataRows > 0) {
                    for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                        for ($c = $firstDayColIdx; $c <= $totalColIdx; $c++) {
                            $cell = $sheet->getCellByColumnAndRow($c, $r);
                            $val  = $cell->getValue();
                            if ($val === null || $val === '') {
                                $cell->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                    // Formato "0" para todo el rango numérico
                    $sheet->getStyle("C{$dataStartRow}:{$totalColLetter}{$dataEndRow}")
                        ->getNumberFormat()->setFormatCode('0');
                }

                // Alineaciones
                if ($dataRows > 0) {
                    $sheet->getStyle("B{$dataStartRow}:B{$dataEndRow}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$dataStartRow}:{$totalColLetter}{$dataEndRow}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }

                // Zebra vertical en columnas de días (C .. penúltima)
                if ($dataRows > 0) {
                    for ($c = $firstDayColIdx; $c < $totalColIdx; $c++) {
                        if ((($c - $firstDayColIdx) % 2) === 1) {
                            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$dataEndRow}")
                                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC');
                        }
                    }
                }

                // ===== Pies (dos últimas filas) =====
                $lastRow = (int) $sheet->getHighestRow();
                $footer2 = $lastRow;       // "Total V.T. (...)"
                $footer1 = $lastRow - 1;   // "Total Salidas"

                foreach ([$footer1, $footer2] as $fr) {
                    $sheet->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['argb'=>$footerFill]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$fontBlack], 'size'=>10],
                        'borders' => ['outline' => ['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb'=>$blueDark]]],
                    ]);
                    $sheet->getStyle("A{$fr}:B{$fr}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$fr}:{$lastCol}{$fr}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$fr}:{$lastCol}{$fr}")
                        ->getNumberFormat()->setFormatCode('0');

                    // También rellenar 0 si algo quedó vacío en pies
                    for ($c = $firstDayColIdx; $c <= $totalColIdx; $c++) {
                        $cell = $sheet->getCellByColumnAndRow($c, $fr);
                        $val  = $cell->getValue();
                        if ($val === null || $val === '') {
                            $cell->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        }
                    }
                    $sheet->getRowDimension($fr)->setRowHeight(18);
                }
            }
        ];
    }


    /* ----------------- Datos & Helpers ----------------- */

    protected function prepare(): void
    {
        if ($this->daysInMonth > 0) return;

        // Asegurar columnas existentes
        if (!Schema::hasColumn('departures', $this->dateColumn)) {
            $this->dateColumn = 'date';
        }
        if ($this->countColumn === null) {
            foreach (['laps','num','quantity','vueltas','count','total_turns'] as $c) {
                if (Schema::hasColumn('departures', $c)) { $this->countColumn = $c; break; }
            }
        }

        $start = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;
        $this->days        = range(1, $this->daysInMonth);

        // 1) Vehículos ACTIVOS en orden
        $orderCol = Schema::hasColumn('vehicles', 'sort_order')
            ? 'sort_order'
            : (Schema::hasColumn('vehicles', 'order') ? 'order'
                : (Schema::hasColumn('vehicles', 'orden') ? 'orden'
                    : (Schema::hasColumn('vehicles','plate') ? 'plate' : 'id')));

        $vehQ = DB::table('vehicles')->select('id', 'plate');
        if (Schema::hasColumn('vehicles', 'status')) {
            $vehQ->where('status', 'active');
        }
        $vehicles = $vehQ->orderBy($orderCol)->get();

        $this->rows = [];
        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'plate' => (string)$v->plate,
                'daily' => array_fill(1, $this->daysInMonth, 0),
                'total' => 0,
            ];
        }

        // 2) Agregados de departures (COUNT(*) o SUM(col)) dentro del mes
        $dateCol   = $this->dateColumn;
        $selectRaw = $this->countColumn
            ? "vehicle_id, DAY($dateCol) as d, SUM({$this->countColumn}) as s"
            : "vehicle_id, DAY($dateCol) as d, COUNT(*) as s";

        $aggs = DB::table('departures')
            ->selectRaw($selectRaw)
            ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
            ->groupBy('vehicle_id', 'd')
            ->get();

        foreach ($aggs as $r) {
            $vid = (int) $r->vehicle_id;
            $d   = (int) $r->d;
            $s   = (float) $r->s;
            if (!isset($this->rows[$vid])) continue; // ignora vehículos no activos

            // ÷2 con redondeo half-up (misma lógica del módulo)
            $halved = (int) round($s / 2, 0, PHP_ROUND_HALF_UP);
            $this->rows[$vid]['daily'][$d] = $halved;
        }

        // 3) Totales por fila / día
        $this->totalPerDay = array_fill(1, $this->daysInMonth, 0);
        foreach ($this->rows as &$row) {
            $row['total'] = array_sum($row['daily']);
            foreach ($row['daily'] as $d => $val) $this->totalPerDay[$d] += $val;
        }
        unset($row);

        // 4) Vehículos con salida por día (#daily > 0)
        $this->vehiclesWorkedPerDay = array_fill(1, $this->daysInMonth, 0);
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $worked = 0;
            foreach ($this->rows as $row) if (($row['daily'][$d] ?? 0) > 0) $worked++;
            $this->vehiclesWorkedPerDay[$d] = $worked;
        }
    }

    protected function monthName(): string
    {
        $m = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        return $m[$this->month] ?? '';
    }
}
