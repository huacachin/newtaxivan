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
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class DeparturesMonthly implements FromArray, WithHeadings, WithStyles, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected int $year,
        protected int $month,
        protected string $dateColumn = 'date',      // cambia si usas otro nombre de columna
        protected ?string $countColumn = null       // si existe (p.ej. 'laps'), se suma; si no, COUNT(*)
    ) {}

    protected int   $daysInMonth = 0;
    protected array $days = [];
    protected array $rows = [];                 // [ [plate, daily[1..n], total], ... ]
    protected array $totalPerDay = [];
    protected array $vehiclesWorkedPerDay = [];
    protected array $sundayCols = [];           // índices (1-based) de columnas de domingo

    /* ----------------- Datos → headings / array ----------------- */

    public function headings(): array
    {
        $this->prepare();
        $head = ['Item', 'Placa'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $head[] = (string)$d;
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
            for ($d=1; $d <= $this->daysInMonth; $d++) $row[] = (int)($r['daily'][$d] ?? 0);
            $row[] = (int)$r['total'];
            $data[] = $row;
        }

        if (!empty($data)) {
            $data[] = array_fill(0, 2 + $this->daysInMonth + 1, ''); // separador
        }

        // Totales: “Total Salidas”
        $rowA = ['', 'Total Salidas'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowA[] = (int)($this->totalPerDay[$d] ?? 0);
        $rowA[] = array_sum($this->totalPerDay);
        $data[] = $rowA;

        // Totales: “Total V.T. (vehículos con salida)”
        $rowB = ['', 'Total V.T. (vehículos con salida)'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowB[] = (int)($this->vehiclesWorkedPerDay[$d] ?? 0);
        $rowB[] = array_sum($this->vehiclesWorkedPerDay);
        $data[] = $rowB;

        return $data;
    }

    /* ----------------- Estilos ----------------- */

    public function styles(Worksheet $sheet)
    {
        // La cabecera quedará en fila 2 (inserto un título en fila 1 en AfterSheet)
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Header bold + centrado
        $sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true);
        $sheet->getStyle("A2:{$lastCol}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Números centrados
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Placa alineada izquierda
        $sheet->getStyle('B3:B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();

                // Insertar título (fila 1)
                $sheet->insertNewRowBefore(1, 1);
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $title = 'REPORTE MENSUAL POR PLACA – V.T '.strtoupper($this->monthName()).' '.$this->year;
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('1F2937'); // banda oscura

                // Cabecera (fila 2) con color
                $sheet->getStyle("A2:{$lastCol}2")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('0EA5E9'); // celeste
                $sheet->getStyle("A2:{$lastCol}2")->getFont()->getColor()->setRGB('FFFFFF');

                // AutoFilter + Freeze: 2 primeras columnas y encabezado
                $sheet->setAutoFilter("A2:{$lastCol}2");
                $sheet->freezePane('C3');

                // Anchos
                $sheet->getColumnDimension('A')->setWidth(8);   // Item
                $sheet->getColumnDimension('B')->setWidth(18);  // Placa

                // Bordes finos
                $sheet->getStyle("A2:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('D0D7E2');

                // Cebra para columnas de días (C..penúltima)
                $firstDayColIdx = 3; // A=1,B=2, días desde C
                $lastColIdx     = Coordinate::columnIndexFromString($lastCol);
                $totalColIdx    = $lastColIdx; // última = T. Salida
                for ($c = $firstDayColIdx; $c <= $totalColIdx; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    if ($c < $totalColIdx && (($c - $firstDayColIdx) % 2 === 1)) {
                        $sheet->getStyle("{$col}3:{$col}{$lastRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8FAFC');
                    }
                    $sheet->getStyle("{$col}3:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('0');
                }

                // Domingos en rojo (cabecera + columna completa)
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EF4444');
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getFont()->getColor()->setRGB('FFFFFF');
                }

                // Filas de totales (últimas 2)
                // Detecta dónde empiezan (después de data + 1 sep)
                $dataRows = count($this->rows);
                $startTotals = 3 + $dataRows + ($dataRows ? 1 : 0);
                if ($dataRows === 0) $startTotals = 3;

                // Total Salidas → azul claro
                $sheet->getStyle("A{$startTotals}:{$lastCol}{$startTotals}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->getStyle("A{$startTotals}:{$lastCol}{$startTotals}")
                    ->getFont()->setBold(true);

                // Total V.T. → lila claro
                $sheet->getStyle("A".($startTotals+1).":{$lastCol}".($startTotals+1))
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('EDE9FE');
                $sheet->getStyle("A".($startTotals+1).":{$lastCol}".($startTotals+1))
                    ->getFont()->setBold(true);
            }
        ];
    }

    /* ----------------- Helpers & datos ----------------- */

    protected function prepare(): void
    {
        if ($this->daysInMonth > 0) return;

        // Ajustes de columnas disponibles
        if (!Schema::hasColumn('departures', $this->dateColumn)) {
            $this->dateColumn = 'date';
        }
        if ($this->countColumn === null) {
            foreach (['num','quantity','laps','vueltas','count','total_turns'] as $c) {
                if (Schema::hasColumn('departures', $c)) { $this->countColumn = $c; break; }
            }
        }

        $start = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $this->daysInMonth = (int)$start->daysInMonth;
        $this->days        = range(1, $this->daysInMonth);

        // columnas: A=1,B=2, días desde C=3
        $firstDayColIdx = 3;
        foreach ($this->days as $d) {
            if ($start->copy()->day($d)->isSunday()) {
                $this->sundayCols[] = $firstDayColIdx + ($d - 1);
            }
        }

        // 1) Traer vehículos en orden
        $orderCol = Schema::hasColumn('vehicles', 'order')
            ? 'order'
            : (Schema::hasColumn('vehicles', 'orden') ? 'orden' : 'plate');

        $vehicles = DB::table('vehicles')->select('id','plate')->orderBy($orderCol)->get();

        $this->rows = [];
        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'plate' => (string)$v->plate,
                'daily' => array_fill(1, $this->daysInMonth, 0),
                'total' => 0,
            ];
        }

        // 2) Agregados de departures (COUNT(*) o SUM(col))
        $dateCol = $this->dateColumn;
        $selectRaw = $this->countColumn
            ? "vehicle_id, DAY($dateCol) as d, SUM({$this->countColumn}) as s"
            : "vehicle_id, DAY($dateCol) as d, COUNT(*) as s";

        $aggs = DB::table('departures')
            ->selectRaw($selectRaw)
            ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
            ->groupBy('vehicle_id', 'd')
            ->get();

        foreach ($aggs as $r) {
            $vid = (int)$r->vehicle_id;
            $d   = (int)$r->d;
            $s   = (float)$r->s;
            if (!isset($this->rows[$vid])) continue;

            // ÷2 con redondeo half-up (igual que en tu componente)
            $halved = (int) round($s / 2, 0, PHP_ROUND_HALF_UP);
            $this->rows[$vid]['daily'][$d] = $halved;
        }

        // 3) Totales por fila y por día + vehículos trabajados
        $this->totalPerDay = array_fill(1, $this->daysInMonth, 0);
        foreach ($this->rows as &$row) {
            $row['total'] = array_sum($row['daily']);
            foreach ($row['daily'] as $d => $val) $this->totalPerDay[$d] += $val;
        }
        unset($row);

        $this->vehiclesWorkedPerDay = array_fill(1, $this->daysInMonth, 0);
        for ($d=1; $d <= $this->daysInMonth; $d++) {
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
