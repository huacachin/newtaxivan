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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
                $row[] = ($r['type'] === 'S/') ? (float)$val : (int)$val; // siempre número -> 0 visibles
            }
            $row[] = (int)   ($r['total_sal']   ?? 0);
            $row[] = (float) ($r['total_soles'] ?? 0);
            $data[] = $row;
        }

        // Totales (sin separador)
        $rowA = ['', '', 'Salidas'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowA[] = (int)($this->totalsSalidas[$d] ?? 0);
        $rowA[] = (int)$this->grandSalidas;
        $rowA[] = 0;
        $data[] = $rowA;

        $rowB = ['', '', 'S/'];
        for ($d=1; $d <= $this->daysInMonth; $d++) $rowB[] = (float)($this->totalsMonto[$d] ?? 0);
        $rowB[] = 0;
        $rowB[] = (float)$this->grandMonto;
        $data[] = $rowB;

        return $data;
    }

    public function styles(Worksheet $sheet) { return []; }

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
                $borderC    = 'FFCFD8DC';
                $sunRed     = 'FFEF4444';
                $moneyRed   = 'FFCC0000';


                // Fuente global 10pt
                $s->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // ===== Título
                $s->insertNewRowBefore(1, 1);
                $lastCol    = $s->getHighestColumn();
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);
                $title = 'REPORTE ESTADÍSTICO DE SALIDAS – ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
                $s->mergeCells("A1:{$lastCol}1");
                $s->setCellValue('A1', $title);
                $s->getRowDimension(1)->setRowHeight(18);
                $s->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$fontW]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$moneyRed]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // ===== Encabezado
                $headerRow    = 2;
                $dataStartRow = 3;
                $s->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$fontW]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $s->getRowDimension($headerRow)->setRowHeight(18);

                // Domingos (solo header)
                $firstDayColIdx = 4; // D
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $s->getStyle("{$col}{$headerRow}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$sunRed]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                    ]);
                }

                // Freeze
                $s->freezePane('D3');

                // ===== Bordes
                $lastRow = (int)$s->getHighestRow();
                $s->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderC);

                // ===== Anchos compactos
                for ($c=1; $c <= $lastColIdx; $c++) {
                    $s->getColumnDimensionByColumn($c)->setAutoSize(false);
                }
                $s->getColumnDimension('A')->setWidth(14.5); // CONTROLADOR
                $s->getColumnDimension('B')->setWidth(11);   // PARADERO
                $s->getColumnDimension('C')->setWidth(6.5);  // TIPO
                // D..(penúltima): días
                for ($c=$firstDayColIdx; $c < $lastColIdx-1; $c++) {
                    $s->getColumnDimensionByColumn($c)->setWidth(3.7);
                }
                // Penúltima = SALIDAS, Última = S/
                $s->getColumnDimensionByColumn($lastColIdx-1)->setWidth(7.0);
                $s->getColumnDimensionByColumn($lastColIdx  )->setWidth(8.0);

                // === +1 punto de ancho D..AG (sin exceder la última col real)
                $fromIdx = Coordinate::columnIndexFromString('D');
                $toIdx   = Coordinate::columnIndexFromString('AG');
                $endIdx  = min($toIdx, $lastColIdx);
                for ($c = $fromIdx; $c <= $endIdx; $c++) {
                    $dim = $s->getColumnDimensionByColumn($c);
                    $current = (float) $dim->getWidth();
                    if ($current <= 0.0) { $current = 3.0; }
                    $dim->setWidth($current + 1.0);
                }

                // ===== Alineaciones
                $dataRows   = count($this->rows);
                $dataEndRow = $dataRows > 0 ? ($dataStartRow + $dataRows - 1) : ($dataStartRow - 1);
                $lastColL   = Coordinate::stringFromColumnIndex($lastColIdx);
                if ($dataRows > 0) {
                    $s->getStyle("A{$dataStartRow}:A{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("B{$dataStartRow}:B{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("C{$dataStartRow}:{$lastColL}{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ===== Columna C (TIPO) con fondo azul en todo el cuerpo
                if ($dataRows > 0) {
                    $s->getStyle("C{$dataStartRow}:C{$dataEndRow}")->applyFromArray([
                        'fill'  => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                        'font'  => ['bold'=>true,'color'=>['argb'=>$fontW],'size'=>10],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // ===== Zebra vertical en días
                if ($dataRows > 0) {
                    for ($c = $firstDayColIdx; $c < $lastColIdx-1; $c++) {
                        if ((($c - $firstDayColIdx) % 2) === 1) {
                            $col = Coordinate::stringFromColumnIndex($c);
                            $s->getStyle("{$col}{$dataStartRow}:{$col}{$dataEndRow}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC');
                        }
                    }
                }

                // ===== Formatos y ceros (datos) + fila S/ en rojo
                if ($dataRows > 0) {
                    $salidasColIdx = $lastColIdx - 1;
                    $montoColIdx   = $lastColIdx;

                    for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                        $from = Coordinate::stringFromColumnIndex($firstDayColIdx);
                        $to   = Coordinate::stringFromColumnIndex($salidasColIdx - 1);

                        // Días (ceros + formato "0")
                        if ($salidasColIdx - 1 >= $firstDayColIdx) {
                            for ($c=$firstDayColIdx; $c <= $salidasColIdx - 1; $c++) {
                                $cell = $s->getCellByColumnAndRow($c, $r);
                                if ($cell->getValue() === null || $cell->getValue() === '') {
                                    $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                                }
                            }
                            $s->getStyle("{$from}{$r}:{$to}{$r}")
                                ->getNumberFormat()->setFormatCode('0');
                        }

                        // Totales por fila (ambos como enteros según lineamientos)
                        foreach ([$salidasColIdx, $montoColIdx] as $colIdx) {
                            $cell = $s->getCellByColumnAndRow($colIdx, $r);
                            if ($cell->getValue() === null || $cell->getValue() === '') {
                                $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                            }
                            $s->getStyle(Coordinate::stringFromColumnIndex($colIdx).$r)
                                ->getNumberFormat()->setFormatCode('0');
                        }

                        // Fila tipo S/ en rojo
                        $type = (string)$s->getCell("C{$r}")->getValue();
                        if ($type === 'S/') {
                            $s->getStyle("A{$r}:{$lastColL}{$r}")
                                ->getFont()->getColor()->setARGB($white);
                        }
                    }
                }

                // ===== Merge Paradero (B) de 2 en 2 + badge azul
                $i = 0;
                while ($i < $dataRows) {
                    $row1 = $dataStartRow + $i;
                    $row2 = $row1;
                    if ($i + 1 < $dataRows) {
                        $r1 = $this->rows[$i];
                        $r2 = $this->rows[$i+1];
                        if ($r2['controller'] === $r1['controller'] && $r2['stop'] === $r1['stop']) {
                            $row2 = $row1 + 1; $i += 2;
                        } else { $i += 1; }
                    } else { $i += 1; }

                    if ($row2 > $row1) $s->mergeCells("B{$row1}:B{$row2}");
                    $s->getStyle("B{$row1}:B{$row2}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // ===== Merge Controlador (A) por bloque + badge azul
                if ($dataRows > 0) {
                    $ctrlStart = $dataStartRow;
                    $prevCtrl  = $this->rows[0]['controller'];
                    for ($k=1; $k < $dataRows; $k++) {
                        $curr = $this->rows[$k]['controller'];
                        if ($curr !== $prevCtrl) {
                            $ctrlEnd = $dataStartRow + $k - 1;
                            $s->mergeCells("A{$ctrlStart}:A{$ctrlEnd}");
                            $s->getStyle("A{$ctrlStart}:A{$ctrlEnd}")->applyFromArray([
                                'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                                'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
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
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // ===== Totales (últimas 2 filas) + TOTAL GENERAL
                $lastRow = (int)$s->getHighestRow();
                $footerS = $lastRow - 1; // Salidas
                $footerM = $lastRow;     // S/
                foreach ([$footerS, $footerM] as $fr) {
                    for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                        $cell = $s->getCellByColumnAndRow($c, $fr);
                        if ($cell->getValue() === null || $cell->getValue() === '') {
                            $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                        }
                    }
                    $s->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerFill]],
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontB],'size'=>10],
                        'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blueDark]]],
                        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                    $s->getStyle("D{$fr}:{$lastColL}{$fr}")->getNumberFormat()->setFormatCode('0');
                    $s->getStyle("A{$fr}:B{$fr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("C{$fr}:{$lastColL}{$fr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getRowDimension($fr)->setRowHeight(18);
                }

                // TOTAL GENERAL en A (2 filas fusionadas)
                $s->mergeCells("A{$footerS}:A{$footerM}");
                $s->setCellValue("A{$footerS}", 'TOTAL GENERAL');
                $s->getStyle("A{$footerS}:A{$footerM}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $s->getStyle("B{$footerS}:B{$footerM}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $s->getStyle("C{$footerS}:C{$footerM}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
