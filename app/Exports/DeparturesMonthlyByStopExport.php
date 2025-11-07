<?php
// app/Exports/DeparturesMonthlyByStopExport.php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class DeparturesMonthlyByStopExport implements FromArray, WithHeadings, WithStyles, WithEvents, ShouldAutoSize
{
    public function __construct(
        protected int $year,
        protected int $month
    ) {}

    protected int   $daysInMonth = 0;
    protected array $rows = [];
    protected array $totalsTE = [];
    protected array $totalsTA = [];
    protected array $totalsVT = [];
    protected int   $grandTE = 0;
    protected int   $grandTA = 0;
    protected int   $grandVT = 0;
    protected array $sundayCols = []; // índices 1-based de columna (encabezado de días)

    /* ====================== Headings & Data ====================== */

    public function headings(): array
    {
        $this->prepareDataIfNeeded();
        $head = ['CONTROLADOR','PARADERO','TIPO'];
        for ($d=1; $d <= $this->daysInMonth; $d++) { $head[] = (string)$d; }
        $head[] = 'V.T';
        return $head;
    }

    public function array(): array
    {
        $this->prepareDataIfNeeded();

        $data = [];
        foreach ($this->rows as $r) {
            $row = [
                $r['controller'],
                $r['stop'],
                ($r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.'),
            ];
            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $row[] = (int)($r['days'][$d] ?? 0); // siempre numérico → muestra 0
            }
            $row[] = (int)$r['total'];
            $data[] = $row;
        }

        // Totales T.E
        $rowTE = ['', '', 'T.E'];
        for ($d=1; $d <= $this->daysInMonth; $d++) { $rowTE[] = (int)$this->totalsTE[$d]; }
        $rowTE[] = (int)$this->grandTE;
        $data[]  = $rowTE;

        // Totales T.A
        $rowTA = ['', '', 'T.A'];
        for ($d=1; $d <= $this->daysInMonth; $d++) { $rowTA[] = (int)$this->totalsTA[$d]; }
        $rowTA[] = (int)$this->grandTA;
        $data[]  = $rowTA;

        // Totales V.T
        $rowVT = ['', '', 'V.T'];
        for ($d=1; $d <= $this->daysInMonth; $d++) { $rowVT[] = (int)$this->totalsVT[$d]; }
        $rowVT[] = (int)$this->grandVT;
        $data[]  = $rowVT;

        return $data;
    }

    public function styles(Worksheet $sheet) { return []; }

    /* ====================== Diseño (AfterSheet) ====================== */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $s = $e->sheet->getDelegate();

                // Paleta exacta
                $blueDark   = 'FF2874A6'; // encabezados / títulos
                $footerFill = 'FFCEE7FF'; // pies (totales)
                $fontW      = 'FFFFFFFF';
                $fontB      = 'FF000000';
                $borderC    = 'FFCFD8DC';
                $sunRed     = 'FFEF4444'; // domingos (header de días)
                $white = 'FFFFFF';

                // Tipografía compacta
                $s->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // ===== Título (fila 1)
                $s->insertNewRowBefore(1, 1);
                $lastCol = $s->getHighestColumn();
                $title   = 'REPORTE MENSUAL POR PARADERO V.T. — ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
                $s->mergeCells("A1:{$lastCol}1");
                $s->setCellValue('A1', $title);
                $s->getRowDimension(1)->setRowHeight(18);
                $s->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$white]],
                    'font' => ['bold'=>true, 'size'=>10, 'color'=>['argb'=>$sunRed]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // ===== Cabecera (fila 2)
                $headerRow    = 2;
                $dataStartRow = 3;
                $s->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true, 'size'=>10, 'color'=>['argb'=>$fontW]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $s->getRowDimension($headerRow)->setRowHeight(18);

                // Domingos en rojo (solo header de días)
                $firstDayColIdx = 4; // días desde D
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $s->getStyle("{$col}{$headerRow}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$sunRed]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$fontW]],
                    ]);
                }

                // Congelar filas 1-2 y columnas A-C
                //$s->freezePane('D3');

                // ===== Bordes finos a todo
                $lastRow = (int)$s->getHighestRow();
                $s->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderC);

                // ===== Anchos compactos (desactiva autosize y fija)
                $lastColIdx = Coordinate::columnIndexFromString($lastCol);
                for ($c=1; $c <= $lastColIdx; $c++) {
                    $s->getColumnDimensionByColumn($c)->setAutoSize(false);
                }
                $s->getColumnDimension('A')->setWidth(14.5); // CONTROLADOR
                $s->getColumnDimension('B')->setWidth(16);   // PARADERO
                $s->getColumnDimension('C')->setWidth(6.5);  // TIPO
                for ($c=$firstDayColIdx; $c < $lastColIdx; $c++) {
                    $s->getColumnDimensionByColumn($c)->setWidth(3.0); // Días
                }
                $s->getColumnDimensionByColumn($lastColIdx)->setWidth(6.5); // V.T

                // ===== Alineaciones / formatos
                $dataRows   = count($this->rows);
                $dataEndRow = $dataRows > 0 ? ($dataStartRow + $dataRows - 1) : ($dataStartRow - 1);
                $lastColLetter = Coordinate::stringFromColumnIndex($lastColIdx);

                if ($dataRows > 0) {
                    // A y B a la izquierda; resto centrado
                    $s->getStyle("A{$dataStartRow}:A{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("B{$dataStartRow}:B{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $s->getStyle("C{$dataStartRow}:{$lastCol}{$dataEndRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // ---- Rellenar celdas vacías con 0 en días + V.T (datos)
                    for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                        for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                            $cell = $s->getCellByColumnAndRow($c, $r);
                            $val  = $cell->getValue();
                            if ($val === null || $val === '') {
                                $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                    // Formato entero "0" a todo el rango numérico de datos
                    $s->getStyle("D{$dataStartRow}:{$lastColLetter}{$dataEndRow}")
                        ->getNumberFormat()->setFormatCode('0');
                }

                // ===== Zebra vertical suave (días: D .. penúltima)
                if ($dataRows > 0) {
                    for ($c = $firstDayColIdx; $c < $lastColIdx; $c++) {
                        if ((($c - $firstDayColIdx) % 2) === 1) {
                            $col = Coordinate::stringFromColumnIndex($c);
                            $s->getStyle("{$col}{$dataStartRow}:{$col}{$dataEndRow}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF8FAFC');
                        }
                    }
                }

                $s->getStyle("C{$dataStartRow}:C{$dataEndRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['color'=>['argb'=>$white], 'bold'=>true, 'size'=>10],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // ===== Merge y estilo por bloques =====
                // 1) PARADERO (col B): merge de 2 en 2 (Emp./Apoyo) + azul
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
                        'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$fontW]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT, 'vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // 2) CONTROLADOR (col A): merge por bloque del mismo controlador + azul
                if ($dataRows > 0) {
                    $ctrlStart = $dataStartRow;
                    $prevCtrl  = $this->rows[0]['controller'];
                    for ($k=1; $k < $dataRows; $k++) {
                        $curr = $this->rows[$k]['controller'];
                        if ($curr !== $prevCtrl) {
                            $ctrlEnd = $dataStartRow + $k - 1;
                            $s->mergeCells("A{$ctrlStart}:A{$ctrlEnd}");
                            $s->getStyle("A{$ctrlStart}:A{$ctrlEnd}")->applyFromArray([
                                'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                                'font' => ['bold'=>true, 'color'=>['argb'=>$fontW]],
                                'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT, 'vertical'=>Alignment::VERTICAL_CENTER],
                            ]);
                            $ctrlStart = $ctrlEnd + 1;
                            $prevCtrl  = $curr;
                        }
                    }
                    // último bloque
                    $ctrlEnd = $dataStartRow + $dataRows - 1;
                    $s->mergeCells("A{$ctrlStart}:A{$ctrlEnd}");
                    $s->getStyle("A{$ctrlStart}:A{$ctrlEnd}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$fontW]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT, 'vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                }

                // ===== Totales (T.E / T.A / V.T) =====
                $lastRow = (int)$s->getHighestRow();
                $footerVT = $lastRow;       // V.T
                $footerTA = $lastRow - 1;   // T.A
                $footerTE = $lastRow - 2;   // T.E

                foreach ([$footerTE, $footerTA, $footerVT] as $fr) {
                    // Rellenar celdas vacías con 0 en totales (días + V.T)
                    for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                        $cell = $s->getCellByColumnAndRow($c, $fr);
                        $val  = $cell->getValue();
                        if ($val === null || $val === '') {
                            $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                        }
                    }
                    // Estilo de pie (celeste + borde) y formato entero
                    $s->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$footerFill]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$fontB], 'size'=>10],
                        'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM, 'color'=>['argb'=>$blueDark]]],
                        'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                    $s->getStyle("D{$fr}:{$lastCol}{$fr}")
                        ->getNumberFormat()->setFormatCode('0'); // enteros
                }

                // “TOTAL GENERAL” fusionado en A (3 filas) con azul
                $s->mergeCells("A{$footerTE}:A{$footerVT}");
                $s->setCellValue("A{$footerTE}", 'TOTAL GENERAL');
                $s->getStyle("A{$footerTE}:A{$footerVT}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontW]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT, 'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // Ajuste de alineación en totales
                $s->getStyle("B{$footerTE}:B{$footerVT}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $s->getStyle("C{$footerTE}:C{$footerVT}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        ];
    }

    /* ====================== Helpers / Datos ====================== */

    protected function monthName(): string
    {
        $m = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        return $m[$this->month] ?? '';
    }

    protected function prepareDataIfNeeded(): void
    {
        if ($this->daysInMonth > 0) return;

        $start = CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
        $end   = $start->endOfMonth();
        $this->daysInMonth = (int)$start->daysInMonth;

        // columnas: A=1,B=2,C=3, días empiezan en D=4
        $dayStartCol = 4;
        for ($d=1; $d <= $this->daysInMonth; $d++) {
            if ($start->day($d)->isSunday()) {
                $this->sundayCols[] = $dayStartCol + ($d-1);
            }
        }

        // === SQL base (agrupa por controlador/paradero y separa Emp/Apoyo) ===
        $sql = <<<SQL
WITH RECURSIVE days(d) AS (
  SELECT 1
  UNION ALL
  SELECT d+1 FROM days WHERE d < DAY(LAST_DAY(:start_date))
),
d0 AS (
  SELECT
    id,
    `date`,
    user_id,
    headquarter_id,
    vehicle_id,
    legacy_plate,
    CASE
      WHEN vehicle_id IS NOT NULL THEN CONCAT('v#', vehicle_id)
      WHEN legacy_plate IS NOT NULL AND legacy_plate <> '' THEN CONCAT('p#', UPPER(TRIM(legacy_plate)))
      ELSE CONCAT('x#', id)
    END AS vkey
  FROM departures
  WHERE `date` BETWEEN :start_ts AND :end_ts
),
base AS (
  SELECT
    u.name          AS controller,
    h.name          AS stop,
    DATE(d0.`date`) AS ddate,

    COUNT(DISTINCT CASE
      WHEN (v1.id IS NOT NULL OR v2.id IS NOT NULL)
      THEN d0.vkey END
    ) AS emp_distinct,

    COUNT(DISTINCT CASE
      WHEN (v1.id IS NULL AND v2.id IS NULL)
      THEN d0.vkey END
    ) AS apoyo_distinct

  FROM d0
  JOIN users        u ON u.id = d0.user_id
  JOIN headquarters h ON h.id = d0.headquarter_id
  LEFT JOIN vehicles v1 ON v1.id = d0.vehicle_id
  LEFT JOIN vehicles v2 ON UPPER(TRIM(v2.plate)) = UPPER(TRIM(d0.legacy_plate))
  GROUP BY u.name, h.name, DATE(d0.`date`)
),
per_day AS (
  SELECT
    controller,
    stop,
    DAY(ddate) AS day,
    emp_distinct   AS emp,
    apoyo_distinct AS apoyo
  FROM base
)
SELECT
  d.d AS day,
  c.controller,
  c.stop,
  COALESCE(SUM(CASE WHEN cday.day = d.d THEN cday.emp   END), 0) AS emp,
  COALESCE(SUM(CASE WHEN cday.day = d.d THEN cday.apoyo END), 0) AS apoyo
FROM days d
LEFT JOIN (SELECT DISTINCT controller, stop FROM per_day) c ON 1=1
LEFT JOIN per_day cday
  ON cday.controller = c.controller AND cday.stop = c.stop
GROUP BY c.controller, c.stop, d.d
ORDER BY c.controller, c.stop, d.d;
SQL;

        $raw = collect(DB::select($sql, [
            'start_date' => $start->format('Y-m-d'),
            'start_ts'   => $start->toDateTimeString(),
            'end_ts'     => $end->toDateTimeString(),
        ]));

        $grouped = $raw->groupBy(fn($r) => ($r->controller ?? '—').'||'.($r->stop ?? '—'));

        $this->rows = [];
        $this->totalsTE = array_fill(1, $this->daysInMonth, 0);
        $this->totalsTA = array_fill(1, $this->daysInMonth, 0);
        $this->totalsVT = array_fill(1, $this->daysInMonth, 0);
        $this->grandTE = $this->grandTA = $this->grandVT = 0;

        foreach ($grouped as $key => $items) {
            [$controller, $stop] = explode('||', $key);

            $empDays = array_fill(1, $this->daysInMonth, 0);
            $apoDays = array_fill(1, $this->daysInMonth, 0);

            foreach ($items as $row) {
                $d = (int)$row->day;
                $empDays[$d] = (int)$row->emp;
                $apoDays[$d] = (int)$row->apoyo;
            }

            $empTotal = array_sum($empDays);
            $apoTotal = array_sum($apoDays);

            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $this->totalsTE[$d] += $empDays[$d];
                $this->totalsTA[$d] += $apoDays[$d];
                $this->totalsVT[$d] += $empDays[$d] + $apoDays[$d];
            }

            $this->grandTE += $empTotal;
            $this->grandTA += $apoTotal;
            $this->grandVT += $empTotal + $apoTotal;

            $this->rows[] = [
                'controller' => $controller,
                'stop'       => $stop,
                'type'       => 'Emp',
                'days'       => $empDays,
                'total'      => $empTotal,
            ];
            $this->rows[] = [
                'controller' => $controller,
                'stop'       => $stop,
                'type'       => 'Apoyo',
                'days'       => $apoDays,
                'total'      => $apoTotal,
            ];
        }
    }
}
