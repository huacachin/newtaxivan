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

    public function headings(): array
    {
        $this->prepareDataIfNeeded();
        $head = ['CONTROLADOR','PARADERO','TIPO'];
        for ($d=1; $d <= $this->daysInMonth; $d++) { $head[] = (string)$d; }
        $head[] = 'TOTAL';
        return $head;
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

        // Fila separadora (opcional)
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
        // La cabecera final quedará en la fila 2 (insertamos título en AfterSheet)
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Encabezado centrado y en negrita
        $sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true);
        $sheet->getStyle("A2:{$lastCol}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Cuerpo centrado
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // CONTROLADOR y PARADERO a la izquierda
        $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B3:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e) {
                $s = $e->sheet->getDelegate();

                // Paleta de la “línea de diseño”
                $bgDark   = 'FF23242F'; // header/footer oscuro
                $fontW    = 'FFFFFFFF';
                $borderC  = 'FFCFD8DC';
                $sunRed   = 'FFEF4444';

                // Insertar título (fila 1)
                $s->insertNewRowBefore(1, 1);
                $lastCol = $s->getHighestColumn();
                $lastRow = $s->getHighestRow();

                // Título
                $title = 'RMP V.T – '.$this->monthName().' '.$this->year;
                $s->setCellValue('A1', $title);
                $s->mergeCells("A1:{$lastCol}1");
                $s->getRowDimension(1)->setRowHeight(28);
                $s->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>14,'color'=>['argb'=>$fontW]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bgDark]],
                ]);

                // Thead con mismo color del título
                $s->getStyle("A2:{$lastCol}2")->applyFromArray([
                    'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bgDark]],
                ]);

                // **Domingos**: solo encabezado de días en rojo
                foreach ($this->sundayCols as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $s->getStyle("{$col}2")->applyFromArray([
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$sunRed]],
                    ]);
                }

                // Bordes finos a todo el rango
                $s->getStyle("A2:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($borderC);

                // Anchos sugeridos en las primeras columnas
                $s->getColumnDimension('A')->setWidth(30); // CONTROLADOR
                $s->getColumnDimension('B')->setWidth(24); // PARADERO
                $s->getColumnDimension('C')->setWidth(10); // TIPO

                // Congelar arriba (título+encabezado) y 3 primeras columnas
                // D3 = mantiene visibles filas 1-2 y columnas A-C
                $s->freezePane('D3');

                // Filas de totales (tfoot): mismo color oscuro que el header
                $dataRows   = count($this->rows);
                $hasSep     = $dataRows > 0 ? 1 : 0; // fila separadora si hubo data
                $totStart   = ($dataRows === 0) ? 3 : 3 + $dataRows + $hasSep;

                for ($r = $totStart; $r <= $totStart + 2; $r++) {
                    $s->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['bold'=>true,'color'=>['argb'=>$fontW]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bgDark]],
                    ]);
                }

                // Formato numérico entero para celdas de días + total (filas de datos y totales)
                $firstDayColIdx = 4; // A=1,B=2,C=3, días desde D
                $lastColIdx     = Coordinate::columnIndexFromString($lastCol);
                for ($c=$firstDayColIdx; $c <= $lastColIdx; $c++) {
                    $colL = Coordinate::stringFromColumnIndex($c);
                    // filas de datos
                    if ($dataRows > 0) {
                        $s->getStyle("{$colL}3:{$colL}".(2+$dataRows))
                            ->getNumberFormat()->setFormatCode('0');
                    }
                    // filas de totales
                    $s->getStyle("{$colL}{$totStart}:{$colL}".($totStart+2))
                        ->getNumberFormat()->setFormatCode('0');
                }
            }
        ];
    }

    /* --------------- helpers / datos --------------- */

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

        // === SQL: contar distintos por controlador/paradero/día separando Emp (match con vehicles) y Apoyo ===
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
