<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeparturesMonthly implements FromArray, WithHeadings, WithStyles, WithEvents
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
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $gray     = 'FF808080';
                $red      = 'FFF80000';
                $redBg    = 'FFFF0000'; // fondo domingos

                $ws->getDefaultRowDimension()->setRowHeight(15);

                // ===== Insertar fila de título =====
                $ws->insertNewRowBefore(1, 1);
                $lastCol    = $ws->getHighestColumn();
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);

                // ===== Título (fila 1) =====
                $title = 'REPORTE MENSUAL POR PLACA – V.T ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', $title);
                $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                // ===== Ocultar cuadrícula =====
                $ws->setShowGridLines(false);

                // ===== Encabezado (fila 2) =====
                $headerRow    = 2;
                $dataStartRow = 3;
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $white]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // ===== Domingos en rojo en el encabezado =====
                $firstDayColIdx = 3; // C = día 1 (A=Item, B=Placa)
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $date = \Carbon\Carbon::create($this->year, $this->month, $d);
                    if ($date->isSunday()) {
                        $col = Coordinate::stringFromColumnIndex($firstDayColIdx + ($d - 1));
                        $ws->getStyle("{$col}{$headerRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $redBg]],
                            'font' => ['bold' => true, 'color' => ['argb' => $white]],
                        ]);
                    }
                }

                // ===== Congelar encabezado =====
                $ws->freezePane('C3');

                // ===== Datos =====
                $dataRows   = count($this->rows);
                $dataEndRow = $dataRows > 0 ? ($dataStartRow + $dataRows - 1) : ($dataStartRow - 1);
                $totalCol   = Coordinate::stringFromColumnIndex($lastColIdx);

                if ($dataRows > 0) {
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$dataEndRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    $ws->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("B{$dataStartRow}:B{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$dataStartRow}:{$totalCol}{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$dataStartRow}:{$totalCol}{$dataEndRow}")->getNumberFormat()->setFormatCode('0');

                    // Forzar 0 en celdas vacías
                    for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                        for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                            $cell = $ws->getCellByColumnAndRow($c, $r);
                            if ($cell->getValue() === null || $cell->getValue() === '') {
                                $cell->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                }

                // ===== Pies (Total Salidas + Total V.T.) =====
                $lastRow = (int) $ws->getHighestRow();
                $footer2 = $lastRow;
                $footer1 = $lastRow - 1;

                foreach ([$footer1, $footer2] as $fr) {
                    if ($fr < $dataStartRow) continue;
                    $ws->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                    $ws->getStyle("A{$fr}:B{$fr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$fr}:{$lastCol}{$fr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$fr}:{$lastCol}{$fr}")->getNumberFormat()->setFormatCode('0');

                    // Rellena 0 si vacío
                    for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                        $cell = $ws->getCellByColumnAndRow($c, $fr);
                        if ($cell->getValue() === null || $cell->getValue() === '') {
                            $cell->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        }
                    }
                    $ws->getRowDimension($fr)->setRowHeight(18);
                }

                // ===== Anchos de columna =====
                $ws->getColumnDimension('A')->setAutoSize(false)->setWidth(6.0);
                $ws->getColumnDimension('B')->setAutoSize(false)->setWidth(9.5);
                for ($c = $firstDayColIdx; $c < $lastColIdx; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    $ws->getColumnDimension($col)->setAutoSize(false)->setWidth(3.0);
                }
                $ws->getColumnDimension($lastCol)->setAutoSize(false)->setWidth(7.0);

                // ===== Ocultar columnas vacías más allá de la tabla =====
                for ($c = $lastColIdx + 1; $c <= 50; $c++) {
                    $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setVisible(false);
                }

                // ===== Fuente 10 global / sin wrap =====
                $finalRow = (int) $ws->getHighestRow();
                $ws->getStyle("A1:{$lastCol}{$finalRow}")->getFont()->setSize(10);
                $ws->getStyle("A1:{$lastCol}{$finalRow}")->getAlignment()->setWrapText(false);
            },
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
