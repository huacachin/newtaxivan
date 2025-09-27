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
                $row[] = ($r['type'] === 'S/') ? (float)$val : (int)$val;
            }
            $row[] = $r['total_sal']   !== null ? (int)$r['total_sal']   : '';
            $row[] = $r['total_soles'] !== null ? (float)$r['total_soles'] : '';
            $data[] = $row;
        }

        if (!empty($data)) {
            $data[] = array_fill(0, 3 + $this->daysInMonth + 2, ''); // separador
        }

        // Totales inferiores
        $rowA = ['', '', 'Salidas'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowA[] = (int)($this->totalsSalidas[$d] ?? 0);
        $rowA[] = (int)$this->grandSalidas;
        $rowA[] = '';
        $data[] = $rowA;

        $rowB = ['', '', 'S/'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowB[] = (float)($this->totalsMonto[$d] ?? 0);
        $rowB[] = '';
        $rowB[] = (float)$this->grandMonto;
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

        // Centrar por defecto
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // A/B alineadas a la izquierda
        $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B3:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();

                // ====== INSERTAR TÍTULO ======
                $sheet->insertNewRowBefore(1, 1);
                $lastCol = $sheet->getHighestColumn();
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);
                $lastRow = $sheet->getHighestRow();

                $title = 'REPORTE ESTADÍSTICO DE SALIDAS – '.$this->monthName().' '.$this->year;
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A1:{$lastCol}1");

                // Título (oscuro)
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'23242F']],
                ]);

                // ====== ENCABEZADO ======
                $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
                    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'23242F']],
                ]);

                // Sin AutoFilter (alineado a la línea de diseño)
                // $sheet->setAutoFilter("A2:{$lastCol}2");

                // Freeze (sobre encabezado y 3 primeras cols)
                $sheet->freezePane('D3');

                // Anchos
                $sheet->getColumnDimension('A')->setWidth(28); // CONTROLADOR
                $sheet->getColumnDimension('B')->setWidth(22); // PARADERO
                $sheet->getColumnDimension('C')->setWidth(10); // TIPO

                // Bordes finos a toda el área
                $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'BFC5D0'],
                        ]
                    ]
                ]);

                // ====== FORMATO NUMÉRICO POR FILA (días) ======
                $firstDayColIdx = 4;                 // D
                $salidasColIdx  = $lastColIdx - 1;   // penúltima
                $montoColIdx    = $lastColIdx;       // última
                $dataStartRow   = 3;
                $dataRows       = count($this->rows);

                // Alinear números a la derecha (días + 2 últimas columnas)
                $sheet->getStyle(
                    Coordinate::stringFromColumnIndex($firstDayColIdx).$dataStartRow.":".
                    $lastCol.$lastRow
                )->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Zebra suave en columnas de días (excluye SALIDAS y S/)
                for ($c = $firstDayColIdx; $c < $salidasColIdx; $c++) {
                    if ((($c - $firstDayColIdx) % 2) === 1) {
                        $col = Coordinate::stringFromColumnIndex($c);
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F9FAFB');
                    }
                }

                // Domingos en rojo (header + datos); el pie se coloreará oscuro después
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EF4444');
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getFont()->getColor()->setRGB('FFFFFF');
                }

                // Formato por fila según tipo (Salidas: 0 | S/: 0.00) en las columnas de días
                for ($r = $dataStartRow; $r < $dataStartRow + $dataRows; $r++) {
                    $type = (string)$sheet->getCell("C{$r}")->getValue();
                    $fmt  = ($type === 'S/') ? '0.00' : '0';
                    if ($salidasColIdx - 1 >= $firstDayColIdx) {
                        $from = Coordinate::stringFromColumnIndex($firstDayColIdx);
                        $to   = Coordinate::stringFromColumnIndex($salidasColIdx - 1);
                        $sheet->getStyle("{$from}{$r}:{$to}{$r}")
                            ->getNumberFormat()->setFormatCode($fmt);
                    }
                }

                // Formato fijo para las dos últimas columnas (totales)
                $salidasColL = Coordinate::stringFromColumnIndex($salidasColIdx);
                $montoColL   = Coordinate::stringFromColumnIndex($montoColIdx);
                $sheet->getStyle("{$salidasColL}{$dataStartRow}:{$salidasColL}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle("{$montoColL}{$dataStartRow}:{$montoColL}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('0.00');

                // ====== TOTALES (dos filas finales, con el mismo color oscuro del diseño) ======
                $startTotals = 3 + $dataRows + ($dataRows ? 1 : 0);
                if ($dataRows === 0) $startTotals = 3;

                // Pie 1: Salidas
                $sheet->getStyle("A{$startTotals}:{$lastCol}{$startTotals}")->applyFromArray([
                    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'23242F']],
                ]);
                // Pie 2: S/
                $sheet->getStyle("A".($startTotals+1).":{$lastCol}".($startTotals+1))->applyFromArray([
                    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'23242F']],
                ]);

                // Reforzar formato numérico en pies
                // Salidas (fila startTotals): días y col SALIDAS -> 0 ; col S/ -> 0.00
                if ($salidasColIdx - 1 >= $firstDayColIdx) {
                    $from = Coordinate::stringFromColumnIndex($firstDayColIdx);
                    $to   = Coordinate::stringFromColumnIndex($salidasColIdx - 1);
                    $sheet->getStyle("{$from}{$startTotals}:{$to}{$startTotals}")
                        ->getNumberFormat()->setFormatCode('0');
                }
                $sheet->getStyle("{$salidasColL}{$startTotals}")
                    ->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle("{$montoColL}{$startTotals}")
                    ->getNumberFormat()->setFormatCode('0.00');

                // S/ (fila startTotals+1): días y col S/ -> 0.00 ; col SALIDAS -> 0
                if ($salidasColIdx - 1 >= $firstDayColIdx) {
                    $from = Coordinate::stringFromColumnIndex($firstDayColIdx);
                    $to   = Coordinate::stringFromColumnIndex($salidasColIdx - 1);
                    $sheet->getStyle("{$from}".($startTotals+1).":{$to}".($startTotals+1))
                        ->getNumberFormat()->setFormatCode('0.00');
                }
                $sheet->getStyle("{$salidasColL}".($startTotals+1))
                    ->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle("{$montoColL}".($startTotals+1))
                    ->getNumberFormat()->setFormatCode('0.00');
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
