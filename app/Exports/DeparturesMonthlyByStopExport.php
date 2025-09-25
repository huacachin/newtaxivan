<?php
// app/Exports/DeparturesMonthlyByStopExport.php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;


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
    protected array $sundayCols = []; // índices de columna (1-based) a colorear

    public function headings(): array
    {
        $this->prepareDataIfNeeded();
        $head = ['CONTROLADOR','PARADERO','TIPO'];
        for ($d=1; $d <= $this->daysInMonth; $d++) { $head[] = (string)$d; }
        $head[] = 'TOTAL';
        return $head;
    }

    protected function monthName(): string
    {
        $m = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        return $m[$this->month] ?? '';
    }


    public function array(): array
    {
        $this->prepareDataIfNeeded();

        $data = [];
        // Filas Emp./Apoyo
        foreach ($this->rows as $r) {
            $row = [
                $r['controller'],
                $r['stop'],
                ($r['type'] === 'Emp' ? 'Emp.' : 'Apoyo.'),
            ];
            for ($d=1; $d <= $this->daysInMonth; $d++) {
                $row[] = (int)($r['days'][$d] ?? 0);
            }
            $row[] = (int)$r['total'];
            $data[] = $row;
        }

        // Fila vacía separadora (opcional)
        if (!empty($data)) $data[] = array_fill(0, 3 + $this->daysInMonth + 1, '');

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

    public function styles(Worksheet $sheet)
    {
        // (La cabecera real quedará en la fila 2 porque insertaremos un título arriba)
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Centrar header y números
        $sheet->getStyle('A2:'.$lastCol.'2')->getFont()->setBold(true);
        $sheet->getStyle('A2:'.$lastCol.'2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // A/B alineadas a la izquierda
        $sheet->getStyle('A3:A'.$lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B3:B'.$lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $sheet    = $e->sheet->getDelegate();

                // ==== Insertar título (fila 1) y mover todo hacia abajo ====
                $sheet->insertNewRowBefore(1, 1);
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $title = 'RMP V.T – '.$this->monthName().' '.$this->year;
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A1:{$lastCol}1");

                // Estilo título
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('1F2937'); // gris-azulado oscuro

                // ==== Cabecera (fila 2) con banda azul suave ====
                $sheet->getStyle("A2:{$lastCol}2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DBEAFE'); // azul muy claro
                $sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true);

                // AutoFilter sobre la cabecera
                $sheet->setAutoFilter("A2:{$lastCol}2");

                // ==== Congelar: encabezado (fila 2) y 3 primeras columnas ====
                $sheet->freezePane('D3');

                // ==== Anchos recomendados primeras columnas ====
                $sheet->getColumnDimension('A')->setWidth(30); // CONTROLADOR
                $sheet->getColumnDimension('B')->setWidth(24); // PARADERO
                $sheet->getColumnDimension('C')->setWidth(10); // TIPO

                // ==== Bordes finos para toda la tabla ====
                $sheet->getStyle("A2:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('D0D7E2');

                // ==== Estética de columnas de días: cebra (col D..Total) ====
                // Índices de columnas: A=1,B=2,C=3, días inician en D=4
                $firstDayColIdx = 4;
                $lastColIdx     = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
                $totalColIdx    = $lastColIdx; // la última es TOTAL

                for ($c = $firstDayColIdx; $c <= $totalColIdx; $c++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    // cebra suave en columnas de días (pares)
                    if ($c < $totalColIdx && (($c - $firstDayColIdx) % 2 === 1)) {
                        $sheet->getStyle("{$colLetter}3:{$colLetter}{$lastRow}")
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F8FAFC'); // gris clarito
                    }
                    // Formato numérico entero
                    $sheet->getStyle("{$colLetter}3:{$colLetter}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('0');
                }

                // ==== Domingos en rojo (sobrescribe la cebra) ====
                foreach ($this->sundayCols as $colIdx) {
                    // OJO: sumamos +1 porque insertamos el título: la fila de cabecera ahora es la 2
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRow}")
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EF4444');
                    $sheet->getStyle("{$colLetter}2:{$colLetter}{$lastRow}")
                        ->getFont()->getColor()->setRGB('FFFFFF');
                }

                // ==== Colorear filas Emp/Apoyo en columnas fijas + TOTAL (no tapa domingos) ====
                $dataStartRow = 3; // primera fila de datos
                $dataRows     = count($this->rows); // incluye Emp y Apoyo
                $hasSepRow    = $dataRows > 0 ? 1 : 0; // en tu array() pusiste fila separadora si hay data

                for ($i = 0; $i < $dataRows; $i++) {
                    $rowNum = $dataStartRow + $i;
                    $type   = $this->rows[$i]['type'] ?? 'Emp';
                    $color  = ($type === 'Emp') ? 'E8F5E9' : 'FFF7ED'; // verde/crema claro

                    // A..C (cols fijas)
                    $sheet->getStyle("A{$rowNum}:C{$rowNum}")
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($color);
                    // TOTAL (última col)
                    $sheet->getStyle("{$lastCol}{$rowNum}:{$lastCol}{$rowNum}")
                        ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($color);
                }

                // ==== Colores de Totales (TE, TA, VT) ====
                // Fila de inicio de totales:
                $totalsStart = $dataStartRow + $dataRows + $hasSepRow;
                if ($dataRows === 0) { $totalsStart = 3; } // si no hubo filas, totales arrancan en 3

                // TE
                $sheet->getStyle("A{$totalsStart}:{$lastCol}{$totalsStart}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE'); // celeste claro
                // TA
                $sheet->getStyle("A".($totalsStart+1).":{$lastCol}".($totalsStart+1))
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF7ED'); // crema (naranja muy claro)
                // VT
                $sheet->getStyle("A".($totalsStart+2).":{$lastCol}".($totalsStart+2))
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('EDE9FE'); // lila muy claro

                // Negrita en las filas de totales
                for ($r = $totalsStart; $r <= $totalsStart+2; $r++) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
                }
            }
        ];
    }

    /* ---------------------- helpers ---------------------- */

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

        // === SQL: Emp. (existe en vehicles id/plate), Apoyo (no existe) ===
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

    protected function colLetter(int $index): string
    {
        // 1 -> A, 2 -> B, ...
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
    }
}
