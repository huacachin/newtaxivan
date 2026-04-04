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
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            $head[] = (string)$d;
        }
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

            // Días
            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $val = $r['days'][$d] ?? 0;
                if ($r['type'] === 'S/') {
                    $row[] = $val != 0 ? $this->formatMoney($val) : '';
                } else {
                    $row[] = $val != 0 ? (int)$val : '';
                }
            }

            // Totales por fila
            $sal = (int)($r['total_sal'] ?? 0);
            $row[] = $sal != 0 ? $sal : '';
            $sol = (float)($r['total_soles'] ?? 0);
            $row[] = $sol != 0 ? $this->formatMoney($sol) : '';

            $data[] = $row;
        }

        // Totales: "Salidas"
        $rowA = ['', '', 'Salidas'];
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            $vs = (int)($this->totalsSalidas[$d] ?? 0);
            $rowA[] = $vs != 0 ? $vs : '';
        }
        $rowA[] = (int)$this->grandSalidas ?: '';
        $rowA[] = '';
        $data[] = $rowA;

        // Totales: "S/"
        $rowB = ['', '', 'S/'];
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            $vm = (float)($this->totalsMonto[$d] ?? 0);
            $rowB[] = $vm != 0 ? $this->formatMoney($vm) : '';
        }
        $rowB[] = '';
        $rowB[] = $this->grandMonto != 0 ? $this->formatMoney($this->grandMonto) : '';
        $data[] = $rowB;

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    /* ================= AfterSheet: diseño ================= */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $s = $e->sheet->getDelegate();

                // Paleta
                $blueDark   = 'FF2874A6';
                $footerFill = 'FFCEE7FF';
                $fontW      = 'FFFFFFFF';
                $fontB      = 'FF000000';
                $borderC    = '000000';
                $sunRed     = 'FFFF0000';
                $moneyRed   = 'F80000';

                // Fuente global
                $s->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // ===== Título =====
                $s->insertNewRowBefore(1, 1);
                $lastCol    = $s->getHighestColumn();
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);
                $lastRow    = (int)$s->getHighestRow();

                $title = 'REPORTE ESTADÍSTICO DE SALIDAS ' . mb_strtoupper($this->monthName()) . ' DEL ' . $this->year;
                $s->mergeCells("A1:{$lastCol}1");
                $s->setCellValue('A1', $title);
                $s->getRowDimension(1)->setRowHeight(18);
                $s->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$fontW]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$moneyRed]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => ['allBorders' => ['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FF000000']]],
                ]);

                // ===== Encabezado =====
                $headerRow    = 2;
                $dataStartRow = 3;

                $s->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$fontW]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $s->getRowDimension($headerRow)->setRowHeight(18);

                // Domingos (solo header de días)
                $firstDayColIdx = 4; // D = día 1
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $s->getStyle("{$col}{$headerRow}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$sunRed]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                    ]);
                }

                // Freeze
                // $s->freezePane(...); // removido

                // ===== Bordes =====
                $lastRow  = (int)$s->getHighestRow();
                $dataRows = count($this->rows);

                // Título + header: sólidos
                $s->getStyle("A1:{$lastCol}{$headerRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($fontB);

                // Datos: contorno y verticales sólidos, horizontales internos discontinuos
                if ($dataRows > 0) {
                    $dataEndRowBorders = $dataStartRow + $dataRows - 1;
                    $borders = $s->getStyle("A{$dataStartRow}:{$lastCol}{$dataEndRowBorders}")->getBorders();
                    $borders->getLeft()  ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($fontB);
                    $borders->getRight() ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($fontB);
                    $borders->getTop()   ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($fontB);
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($fontB);
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($fontB);
                    $borders->getVertical()  ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($fontB);
                }

                // ===== Anchos compactos =====
                for ($c = 1; $c <= $lastColIdx; $c++) {
                    $s->getColumnDimensionByColumn($c)->setAutoSize(false);
                }
                $s->getColumnDimension('A')->setWidth(14.5); // CONTROLADOR
                $s->getColumnDimension('B')->setWidth(11);   // PARADERO
                $s->getColumnDimension('C')->setWidth(6.5);  // TIPO

                // D..(penúltima): días
                for ($c = $firstDayColIdx; $c < $lastColIdx - 1; $c++) {
                    $s->getColumnDimensionByColumn($c)->setWidth(4);
                }
                // Penúltima = SALIDAS, última = S/
                $s->getColumnDimensionByColumn($lastColIdx - 1)->setWidth(7.0);
                $s->getColumnDimensionByColumn($lastColIdx    )->setWidth(8.0);

                // ===== Alineaciones / cuerpo =====
                $dataEndRow = $dataRows > 0 ? ($dataStartRow + $dataRows - 1) : ($dataStartRow - 1);
                $lastColL   = Coordinate::stringFromColumnIndex($lastColIdx);

                if ($dataRows > 0) {
                    $s->getStyle("A{$dataStartRow}:A{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("B{$dataStartRow}:B{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("C{$dataStartRow}:C{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("D{$dataStartRow}:{$lastColL}{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Columna C (TIPO) badge azul
                if ($dataRows > 0) {
                    $s->getStyle("C{$dataStartRow}:C{$dataEndRow}")->applyFromArray([
                        'fill'  => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                        'font'  => ['bold'=>true,'color'=>['argb'=>$fontW],'size'=>10],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'  =>Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // Zebra vertical en días
                if ($dataRows > 0) {
                    for ($c = $firstDayColIdx; $c < $lastColIdx - 1; $c++) {
                        if ((($c - $firstDayColIdx) % 2) === 1) {
                            $col = Coordinate::stringFromColumnIndex($c);
                            $s->getStyle("{$col}{$dataStartRow}:{$col}{$dataEndRow}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC');
                        }
                    }
                }

                // Filas S/ → fuente roja en columnas de días + totales
                if ($dataRows > 0) {
                    $lastColL = Coordinate::stringFromColumnIndex($lastColIdx);
                    for ($k = 0; $k < $dataRows; $k++) {
                        if ($this->rows[$k]['type'] === 'S/') {
                            $fr = $dataStartRow + $k;
                            $s->getStyle("D{$fr}:{$lastColL}{$fr}")
                                ->getFont()->getColor()->setARGB($moneyRed);
                        }
                    }
                }

                // ===== Merge Paradero (B) de 2 en 2 + badge azul =====
                $i = 0;
                while ($i < $dataRows) {
                    $row1 = $dataStartRow + $i;
                    $row2 = $row1;

                    if ($i + 1 < $dataRows) {
                        $r1 = $this->rows[$i];
                        $r2 = $this->rows[$i+1];
                        if ($r2['controller'] === $r1['controller'] && $r2['stop'] === $r1['stop']) {
                            $row2 = $row1 + 1;
                            $i += 2;
                        } else {
                            $i += 1;
                        }
                    } else {
                        $i += 1;
                    }

                    if ($row2 > $row1) {
                        $s->mergeCells("B{$row1}:B{$row2}");
                    }

                    $s->getStyle("B{$row1}:B{$row2}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'  =>Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // ===== Merge Controlador (A) por bloque + badge azul =====
                if ($dataRows > 0) {
                    $ctrlStart = $dataStartRow;
                    $prevCtrl  = $this->rows[0]['controller'];

                    for ($k = 1; $k < $dataRows; $k++) {
                        $curr = $this->rows[$k]['controller'];
                        if ($curr !== $prevCtrl) {
                            $ctrlEnd = $dataStartRow + $k - 1;
                            $s->mergeCells("A{$ctrlStart}:A{$ctrlEnd}");
                            $s->getStyle("A{$ctrlStart}:A{$ctrlEnd}")->applyFromArray([
                                'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                                'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                                'alignment' => [
                                    'horizontal'=>Alignment::HORIZONTAL_LEFT,
                                    'vertical'  =>Alignment::VERTICAL_CENTER,
                                ],
                            ]);
                            $ctrlStart = $ctrlEnd + 1;
                            $prevCtrl  = $curr;
                        }
                    }

                    $ctrlEnd = $dataStartRow + $dataRows - 1;
                    $s->mergeCells("A{$ctrlStart}:A{$ctrlEnd}");
                    $s->getStyle("A{$ctrlStart}:A{$ctrlEnd}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'  =>Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // ===== Totales (últimas 2 filas) + TOTAL GENERAL =====
                $lastRow  = (int)$s->getHighestRow();
                $footerS  = $lastRow - 1; // fila "Salidas"
                $footerM  = $lastRow;     // fila "S/"
                $lastColL = Coordinate::stringFromColumnIndex($lastColIdx);

                // 🔴 Columnas de totales (penúltima = SALIDAS, última = S/)
                $salidasColL = Coordinate::stringFromColumnIndex($lastColIdx - 1);
                $montoColL   = Coordinate::stringFromColumnIndex($lastColIdx);

                foreach ([$footerS, $footerM] as $fr) {
                    $s->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerFill]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontB],'size'=>10],
                        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                    // Bordes: contorno medium azul + internos thin negro
                    $footerBorders = $s->getStyle("A{$fr}:{$lastCol}{$fr}")->getBorders();
                    $footerBorders->getTop()   ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($blueDark);
                    $footerBorders->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($blueDark);
                    $footerBorders->getLeft()  ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($blueDark);
                    $footerBorders->getRight() ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($blueDark);
                    $footerBorders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($fontB);

                    $s->getStyle("A{$fr}:B{$fr}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("C{$fr}:{$lastColL}{$fr}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getRowDimension($fr)->setRowHeight(18);
                }

                // Borde discontinuo entre las dos filas del footer
                $s->getStyle("A{$footerS}:{$lastCol}{$footerS}")
                    ->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($fontB);
                $s->getStyle("A{$footerM}:{$lastCol}{$footerM}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($fontB);

                // Fila S/ del footer → fuente roja en días + total
                $s->getStyle("D{$footerM}:{$lastCol}{$footerM}")
                    ->getFont()->getColor()->setARGB($moneyRed);

                // Merge SALIDAS y S/ (simular rowspan=2 en las últimas dos columnas)
                $s->mergeCells("{$salidasColL}{$footerS}:{$salidasColL}{$footerM}");
                $s->mergeCells("{$montoColL}{$footerS}:{$montoColL}{$footerM}");

// 🔹 Forzamos que el total S/ se vea en la celda superior del merged
                $s->setCellValue("{$montoColL}{$footerS}", $this->formatMoney($this->grandMonto));

                // 🔴 TOTAL GENERAL merge en A:B con rowspan=2 (colspan=2, rowspan=2)
                $s->mergeCells("A{$footerS}:B{$footerM}");
                $s->setCellValue("A{$footerS}", 'TOTAL GENERAL');
                $s->getStyle("A{$footerS}:B{$footerM}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Borde superior discontinuo de TOTAL GENERAL (límite con los datos)
                $s->getStyle("A{$footerS}:B{$footerS}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($fontB);
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
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
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
    u.username  AS controller,
    h.name  AS stop,
    d0.ddate,
    SUM(d0.times) AS salidas,
    SUM(d0.price) AS monto
  FROM d0
  JOIN users        u ON u.id = d0.user_id
  JOIN headquarters h ON h.id = d0.headquarter_id
  GROUP BY u.username, h.name, d0.ddate
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

        $this->rows          = [];
        $this->totalsSalidas = array_fill(1, $this->daysInMonth, 0);
        $this->totalsMonto   = array_fill(1, $this->daysInMonth, 0);
        $this->grandSalidas  = 0;
        $this->grandMonto    = 0;

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

    public function htmlData(): array
    {
        $this->prepare();

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $sundays = [];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $sundays[$d] = $start->day($d)->isSunday();
        }

        return [
            'year'           => $this->year,
            'monthName'      => $this->monthName(),
            'daysInMonth'    => $this->daysInMonth,
            'sundays'        => $sundays,
            'rows'           => $this->rows,
            'totalsSalidas'  => $this->totalsSalidas,
            'totalsMonto'    => $this->totalsMonto,
            'grandSalidas'   => $this->grandSalidas,
            'grandMonto'     => $this->grandMonto,
        ];
    }

    protected function monthName(): string
    {
        $m = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
            5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
            9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];
        return $m[$this->month] ?? '';
    }

    /**
     * Formatea montos:
     * - 0      => "0"
     * - 10     => "10"
     * - 10.25  => "10.25"
     */
    protected function formatMoney(float|int|null $value): string
    {
        $value = (float) ($value ?? 0);

        // Cero explícito
        if (abs($value) < 0.0000001) {
            return '0';
        }

        // Si es entero → sin decimales
        if (fmod($value, 1.0) == 0.0) {
            return number_format($value, 0, '.', '');
        }

        // Con decimales pero sin .00 de más
        $str = number_format($value, 2, '.', '');
        return rtrim(rtrim($str, '0'), '.');
    }
}
