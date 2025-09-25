<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeparturesStatsMonthlyExport implements FromArray, WithHeadings, WithStyles, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected int $year,
        protected int $month
    ) {}

    protected int   $daysInMonth = 0;
    protected array $rows = [];             // pares: Salidas / S/
    protected array $totalsSalidas = [];    // por día
    protected array $totalsMonto   = [];    // por día
    protected int|float $grandSalidas = 0;
    protected int|float $grandMonto   = 0;
    protected array $sundayCols = [];       // índices de columnas (1-based) a pintar

    /* ================= Headings / Data ================= */

    public function headings(): array
    {
        $this->prepare();
        $head = ['CONTROLADOR','PARADERO','TIPO'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $head[] = (string)$d;
        $head[] = 'SALIDAS';
        $head[] = 'S/';
        return $head;
    }

    public function array(): array
    {
        $this->prepare();

        $data = [];
        foreach ($this->rows as $r) {
            $row = [
                $r['controller'],
                $r['stop'],
                $r['type'],
            ];
            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $val = $r['days'][$d] ?? 0;
                if ($r['type'] === 'S/') {
                    $row[] = (float) $val;
                } else {
                    $row[] = (int) $val;
                }
            }
            // Columna SALIDAS y S/ finales
            $row[] = $r['total_sal']   !== null ? (int)$r['total_sal'] : '';
            $row[] = $r['total_soles'] !== null ? (float)$r['total_soles'] : '';
            $data[] = $row;
        }

        if (!empty($data)) {
            $data[] = array_fill(0, 3 + $this->daysInMonth + 2, ''); // separador
        }

        // Totales inferiores
        $rowA = ['', '', 'Salidas'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowA[] = (int)($this->totalsSalidas[$d] ?? 0);
        $rowA[] = (int) $this->grandSalidas;
        $rowA[] = '';
        $data[] = $rowA;

        $rowB = ['', '', 'S/'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowB[] = (float)($this->totalsMonto[$d] ?? 0);
        $rowB[] = '';
        $rowB[] = (float) $this->grandMonto;
        $data[] = $rowB;

        return $data;
    }

    /* ================= Styles ================= */

    public function styles(Worksheet $sheet)
    {
        // La cabecera quedará en la fila 2 (insertaremos un título en la 1)
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Header bold + centrado
        $sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true);
        $sheet->getStyle("A2:{$lastCol}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Centrar datos en general
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // A/B alineadas a la izquierda
        $sheet->getStyle('A3:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B3:B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();

                // Insertar título en fila 1
                $sheet->insertNewRowBefore(1, 1);
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $title = 'REPORTE ESTADÍSTICO DE SALIDAS – '.$this->monthName().' '.$this->year;
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A1:{$lastCol}1");

                // Estilo del título
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');

                // Cabecera (fila 2) celeste
                $sheet->getStyle("A2:{$lastCol}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0EA5E9');
                $sheet->getStyle("A2:{$lastCol}2")->getFont()->getColor()->setRGB('FFFFFF');

                // AutoFilter y Freeze pane (encabezado + 3 primeras cols)
                $sheet->setAutoFilter("A2:{$lastCol}2");
                $sheet->freezePane('D3');

                // Anchos cómodos
                $sheet->getColumnDimension('A')->setWidth(28); // CONTROLADOR
                $sheet->getColumnDimension('B')->setWidth(22); // PARADERO
                $sheet->getColumnDimension('C')->setWidth(12); // TIPO

                // Bordes finos
                $sheet->getStyle("A2:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('D0D7E2');

                // Cebra en columnas de días (desde D hasta antes de las dos últimas)
                $firstDayColIdx = 4; // A=1,B=2,C=3 => D=4
                $lastColIdx     = Coordinate::columnIndexFromString($lastCol);
                $salidasColIdx  = $lastColIdx - 1;
                $montoColIdx    = $lastColIdx;

                for ($c = $firstDayColIdx; $c <= $montoColIdx; $c++) {
                    $col = Coordinate::stringFromColumnIndex($c);
                    // Cebra suave (en días pares)
                    if ($c < $salidasColIdx && (($c - $firstDayColIdx) % 2 === 1)) {
                        $sheet->getStyle("{$col}3:{$col}{$lastRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8FAFC');
                    }
                    // Formato numérico
                    $sheet->getStyle("{$col}3:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode($c === $montoColIdx ? '0.00' : '0');
                }

                // Domingos en rojo (cabecera + columna)
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EF4444');
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getFont()->getColor()->setRGB('FFFFFF');
                }

                // Filas de totales al final (2 filas)
                $dataRows = count($this->rows);
                $startTotals = 3 + $dataRows + ($dataRows ? 1 : 0);
                if ($dataRows === 0) $startTotals = 3;

                // "Salidas" → azul claro
                $sheet->getStyle("A{$startTotals}:{$lastCol}{$startTotals}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->getStyle("A{$startTotals}:{$lastCol}{$startTotals}")
                    ->getFont()->setBold(true);

                // "S/" → lila claro
                $sheet->getStyle("A".($startTotals+1).":{$lastCol}".($startTotals+1))
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('EDE9FE');
                $sheet->getStyle("A".($startTotals+1).":{$lastCol}".($startTotals+1))
                    ->getFont()->setBold(true);
            }
        ];
    }

    /* ================= Helpers / Data ================= */

    protected function prepare(): void
    {
        if ($this->daysInMonth > 0) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int)$start->daysInMonth;

        // Índices de domingos: A=1,B=2,C=3, días desde D=4
        $firstDayColIdx = 4;
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            if ($start->day($d)->isSunday()) {
                $this->sundayCols[] = $firstDayColIdx + ($d - 1);
            }
        }

        // Query (SUM(times) y SUM(price) por Controller/Paradero/día)
        $sql = <<<SQL
WITH RECURSIVE days(d) AS (
  SELECT 1
  UNION ALL
  SELECT d+1 FROM days WHERE d < DAY(LAST_DAY(:start_date))
),
d0 AS (
  SELECT
    d.id,
    d.user_id,
    d.headquarter_id,
    DATE(d.`date`)  AS ddate,
    COALESCE(d.times, 0)  AS times,
    COALESCE(d.price, 0)  AS price
  FROM departures d
  WHERE d.`date` BETWEEN :start_ts AND :end_ts
),
base AS (
  SELECT
    u.name  AS controller,
    h.name  AS stop,
    d0.ddate,
    SUM(d0.times) AS salidas,
    SUM(d0.price) AS monto
  FROM d0
  JOIN users        u ON u.id = d0.user_id
  JOIN headquarters h ON h.id = d0.headquarter_id
  GROUP BY u.name, h.name, d0.ddate
),
per_day AS (
  SELECT
    controller,
    stop,
    DAY(ddate)     AS day,
    salidas,
    monto
  FROM base
)
SELECT
  d.d          AS day,
  c.controller,
  c.stop,
  COALESCE(SUM(CASE WHEN p.day = d.d THEN p.salidas END), 0) AS salidas,
  COALESCE(SUM(CASE WHEN p.day = d.d THEN p.monto   END), 0) AS monto
FROM days d
LEFT JOIN (SELECT DISTINCT controller, stop FROM per_day) c ON 1=1
LEFT JOIN per_day p
  ON p.controller = c.controller AND p.stop = c.stop
GROUP BY c.controller, c.stop, d.d
ORDER BY c.controller, c.stop, d.d;
SQL;

        $raw = collect(DB::select($sql, [
            'start_date' => $start->format('Y-m-d'),
            'start_ts'   => $start->toDateTimeString(),
            'end_ts'     => $end->toDateTimeString(),
        ]));

        $this->rows = [];
        $this->totalsSalidas = array_fill(1, $this->daysInMonth, 0);
        $this->totalsMonto   = array_fill(1, $this->daysInMonth, 0);
        $this->grandSalidas = $this->grandMonto = 0;

        if ($raw->isEmpty()) return;

        $grouped = $raw->groupBy(fn($r) => ($r->controller ?? '—').'||'.($r->stop ?? '—'));

        foreach ($grouped as $key => $items) {
            [$controller, $stop] = explode('||', $key);

            $salidasDays = array_fill(1, $this->daysInMonth, 0);
            $montoDays   = array_fill(1, $this->daysInMonth, 0);

            foreach ($items as $row) {
                $d = (int)$row->day;
                $salidasDays[$d] = (int)   $row->salidas;
                $montoDays[$d]   = (float) $row->monto;
            }

            $salidasTotal = array_sum($salidasDays);
            $montoTotal   = array_sum($montoDays);

            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsSalidas[$d] += $salidasDays[$d];
                $this->totalsMonto[$d]   += $montoDays[$d];
            }

            $this->grandSalidas += $salidasTotal;
            $this->grandMonto   += $montoTotal;

            $this->rows[] = [
                'controller'   => $controller,
                'stop'         => $stop,
                'type'         => 'Salidas',
                'days'         => $salidasDays,
                'total_sal'    => $salidasTotal,
                'total_soles'  => null,
            ];
            $this->rows[] = [
                'controller'   => $controller,
                'stop'         => $stop,
                'type'         => 'S/',
                'days'         => $montoDays,
                'total_sal'    => null,
                'total_soles'  => $montoTotal,
            ];
        }
    }

    protected function monthName(): string
    {
        $m = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        return $m[$this->month] ?? '';
    }
}
