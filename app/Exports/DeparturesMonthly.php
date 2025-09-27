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
                $row[] = (int)($r['daily'][$d] ?? 0);
            }
            $row[] = (int)$r['total'];
            $data[] = $row;
        }

        if (!empty($data)) {
            // separador visual
            $data[] = array_fill(0, 2 + $this->daysInMonth + 1, '');
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

    public function styles(Worksheet $sheet)
    {
        // Con AfterSheet insertamos 2 filas (título y línea de diseño), por lo que:
        // Encabezado real => fila 3. Datos => desde fila 4.
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Header bold + centrado
        $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true);
        $sheet->getStyle("A3:{$lastCol}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Celdas (datos) centradas por defecto
        $sheet->getStyle("A4:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Placa alineada a la izquierda
        $sheet->getStyle("B4:B{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return [];
    }

    /* ----------------- AfterSheet: diseño avanzado ----------------- */

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($e) {
                $sheet = $e->sheet->getDelegate();

                // Insertar 2 filas: 1) título, 2) línea de diseño
                $sheet->insertNewRowBefore(1, 2);
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                /* 1) Banda de título (fila 1) */
                $title = 'REPORTE MENSUAL POR PLACA – V.T ' . strtoupper($this->monthName()) . ' ' . $this->year;
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('23242F'); // banda oscura

                /* 2) Línea de diseño (fila 2) */
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getRowDimension(2)->setRowHeight(6);
                $sheet->getStyle("A2:{$lastCol}2")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E11D48'); // acento

                // Encabezado (fila 3) con color oscuro y texto blanco
                $sheet->getStyle("A3:{$lastCol}3")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('23242F');
                $sheet->getStyle("A3:{$lastCol}3")->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Congelar: encabezado + Item/Placa (dos primeras columnas)
                $sheet->freezePane('C4');

                // AutoFilter: SOLO en Item y Placa (sin filtros en días)
                $sheet->setAutoFilter('A3:B3');

                // Anchos de columnas
                $sheet->getColumnDimension('A')->setWidth(8);   // Item
                $sheet->getColumnDimension('B')->setWidth(18);  // Placa

                // Bordes finos para todo (encabezado + datos + totales)
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('D0D7E2');

                // Zebra para columnas de días (C .. penúltima); total queda fuera
                $firstDayColIdx = 3; // A=1, B=2, días desde C
                $lastColIdx     = Coordinate::columnIndexFromString($lastCol);
                $totalColIdx    = $lastColIdx; // última = T. Salida

                // Rango de datos (sin totales): desde fila 4 hasta fin de registros
                $dataRows     = count($this->rows);
                $dataStartRow = 4;
                $dataEndRow   = $dataRows > 0 ? ($dataStartRow + $dataRows - 1) : 3;

                for ($c = $firstDayColIdx; $c < $totalColIdx; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);

                    // zebra: alterna gris suave
                    if ((($c - $firstDayColIdx) % 2) === 1 && $dataRows > 0) {
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$dataEndRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8FAFC');
                    }
                    // formato entero para días
                    if ($dataRows > 0) {
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$dataEndRow}")
                            ->getNumberFormat()->setFormatCode('0');
                    }
                }

                // Total (última col) entero también en datos
                $totalColLetter = Coordinate::stringFromColumnIndex($totalColIdx);
                if ($dataRows > 0) {
                    $sheet->getStyle("{$totalColLetter}{$dataStartRow}:{$totalColLetter}{$dataEndRow}")
                        ->getNumberFormat()->setFormatCode('0');
                }

                // Filas de totales (dos últimas). Si hay separador, empiezan después.
                $sep          = $dataRows ? 1 : 0; // agregamos una fila en blanco como separador
                $startTotals  = $dataStartRow + $dataRows + $sep;
                if ($dataRows === 0) $startTotals = 4;

                // Footer con el MISMO color del header (#009BDC) y letras blancas
                $footer1 = $startTotals;
                $footer2 = $startTotals + 1;

                foreach ([$footer1, $footer2] as $fr) {
                    $sheet->getStyle("A{$fr}:{$lastCol}{$fr}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('23242F');
                    $sheet->getStyle("A{$fr}:{$lastCol}{$fr}")
                        ->getFont()->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle("A{$fr}:{$lastCol}{$fr}")
                        ->getFont()->setBold(true);
                }

                // Alineaciones de pie
                $sheet->getStyle("A{$footer1}:B{$footer2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("C{$footer1}:{$lastCol}{$footer2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Formato entero para totales
                $sheet->getStyle("C{$footer1}:{$lastCol}{$footer2}")
                    ->getNumberFormat()->setFormatCode('0');
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
