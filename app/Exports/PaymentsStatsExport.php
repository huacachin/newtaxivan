<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentsStatsExport implements FromArray, WithHeadings, WithEvents, WithTitle
{
    protected int $year;
    protected int $month;
    protected int $daysInMonth;

    // cache
    protected array $rows = [];
    protected array $footer = [];

    // tablas/columnas
    protected string $paymentsTable = 'payments';
    protected string $usersTable    = 'users';
    protected string $hqTable       = 'headquarters';
    protected string $amountCol     = 'amount';

    public function __construct(int $year, int $month)
    {
        $this->year  = $year;
        $this->month = $month;
        $this->daysInMonth = (int) CarbonImmutable::create($year, $month, 1)->daysInMonth;

        $this->build();
    }

    public function title(): string
    {
        return "Estadístico {$this->month}-{$this->year}";
    }

    public function headings(): array
    {
        $days = range(1, $this->daysInMonth);

        return array_merge(
            ['CONTROLADOR', 'PARADERO', 'TIPO'],
            array_map(fn($d) => (string)$d, $days),
            ['TOTAL']
        );
    }

    public function array(): array
    {
        return array_merge($this->rows, [$this->footer]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ======= Paleta / base =======
                $blueDark  = 'FF2874A6';  // header/título
                $footerBg  = 'FFCEE7FF';  // pie
                $white     = 'FFFFFFFF';
                $black     = 'FF000000';
                $borderC   = '000000';
                $sundayRed = 'F80000';

                // Tipografía compacta
                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Rango actual (antes de insertar título)
                $lastRow     = (int) $sheet->getHighestRow();      // incluye TOTAL GENERAL
                $lastColStr  =        $sheet->getHighestColumn();  // ej. "AI"
                $lastColIdx  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColStr);
                $endCol      = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIdx);

                // ======= Insertar título en fila 1 =======
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$endCol}1");
                $sheet->setCellValue('A1', "REPORTE ESTADÍSTICO DE PAGO – {$this->mesTexto($this->month)} {$this->year}");
                $sheet->getStyle("A1:{$endCol}1")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$sundayRed]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FF000000']]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(18);

                // ======= THEAD (fila 2) =======
                $sheet->getStyle("A2:{$endCol}2")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // Domingos en rojo (solo header)
                $monthStart = CarbonImmutable::create($this->year, $this->month, 1);
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    if ($monthStart->day($d)->isSunday()) {
                        // D es día 1 → índice 4
                        $col = Coordinate::stringFromColumnIndex(3 + $d);
                        $sheet->getStyle("{$col}2")->applyFromArray([
                            'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$sundayRed]],
                            'font' => ['bold'=>true,'color'=>['argb'=>$white]],
                        ]);
                    }
                }

                // Congelar encabezado + 3 primeras columnas (A..C)
                $sheet->freezePane('D3');

                // ======= Bordes finos a toda la tabla (desde fila 2) =======
                $footerExcelRow = $lastRow + 1; // por la fila de título
                $tableRange = "A2:{$endCol}{$footerExcelRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => $borderC],
                        ],
                    ],
                ]);

                // ======= Formatos =======
                $firstDataRow = 3;                              // primera fila de datos
                $firstDayColI = 4;                              // D
                $firstDayColL = Coordinate::stringFromColumnIndex($firstDayColI);
                $totalColL    = $endCol;                        // última columna = TOTAL

                // DÍAS: decimales opcionales → 0 se ve como "0"; con céntimos muestra hasta 2
                $sheet->getStyle("{$firstDayColL}{$firstDataRow}:{$totalColL}{$footerExcelRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.##');

                // TOTAL (última col): 2 decimales fijos
                $sheet->getStyle("{$totalColL}{$firstDataRow}:{$totalColL}{$footerExcelRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // ======= Alineaciones =======
                $sheet->getStyle("A{$firstDataRow}:{$endCol}{$footerExcelRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ======= "Retraso" siempre en rojo =======
                for ($r = $firstDataRow; $r <= $footerExcelRow - 1; $r++) {
                    if (strcasecmp((string)$sheet->getCell("C{$r}")->getValue(), 'retraso') === 0) {
                        $sheet->getStyle("C{$r}")->getFont()->getColor()->setRGB('F80000');
                    }
                }

                // ======= Anchos compactos =======
                // A: Controlador, B: Paradero, C: Tipo, D..: días, última: Total
                $sheet->getColumnDimension('A')->setWidth(11);
                $sheet->getColumnDimension('B')->setWidth(8);
                $sheet->getColumnDimension('C')->setWidth(7);
                for ($i = 4; $i <= $lastColIdx - 1; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(4.2);
                }
                $sheet->getColumnDimension($endCol)->setWidth(12);

                // ======= Rellenar vacíos numéricos con 0 (días + TOTAL) =======
                for ($r = $firstDataRow; $r <= $footerExcelRow; $r++) {
                    for ($c = 4; $c <= $lastColIdx; $c++) {
                        $cell = $sheet->getCellByColumnAndRow($c, $r);
                        $v    = $cell->getValue();
                        if ($v === null || $v === '') {
                            $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                        }
                    }
                }

                // ======= Pie (TOTAL GENERAL) en #CEE7FF =======
                $sheet->getStyle("A{$footerExcelRow}:{$endCol}{$footerExcelRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    'font' => ['bold'=>true,'color'=>['argb'=>$white]],
                    'borders' => ['outline'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>$borderC]]],
                ]);
            },
        ];
    }



    /* ================== BUILD ================== */

    protected function build(): void
    {
        if (!Schema::hasTable($this->paymentsTable)) {
            $this->rows   = [];
            $this->footer = [];
            return;
        }

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();

        // Mapas de nombres
        $users = Schema::hasTable($this->usersTable)
            ? DB::table($this->usersTable)->pluck('name', 'id')
            : collect();
        $hqs = Schema::hasTable($this->hqTable)
            ? DB::table($this->hqTable)->pluck('name', 'id')
            : collect();

        // Agregación: por usuario + paradero (sucursal) + día + tipo, usando date_register (caja)
        $aggs = DB::table($this->paymentsTable . ' as p')
            ->selectRaw("
                p.user_id,
                p.headquarter_id,
                DAY(p.date_register) as d,
                UPPER(p.type) as t,
                SUM(p.{$this->amountCol}) as s
            ")
            ->whereNotNull('p.date_register')
            ->whereBetween('p.date_register', [$start->toDateString(), $end->toDateString()])
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO','DEUDA'])
            ->groupBy('p.user_id', 'p.headquarter_id', 'd', 't')
            ->get();

        // Estructura: [user][hq]['PAGO'|'RETRASO'|'DEUDA'][day] = sum
        $matrix = [];
        $controllers = []; // para ordenar
        $places = [];      // para ordenar

        foreach ($aggs as $r) {
            $uid = (int) ($r->user_id ?? 0);
            $hq  = (int) ($r->headquarter_id ?? 0);
            $d   = (int) $r->d;
            $t   = (string) $r->t;
            $s   = (float) $r->s;

            $matrix[$uid][$hq][$t][$d] = ($matrix[$uid][$hq][$t][$d] ?? 0.0) + $s;

            if (!isset($controllers[$uid])) $controllers[$uid] = $users[$uid] ?? "Usuario #{$uid}";
            if (!isset($places[$hq]))      $places[$hq]      = $hqs[$hq]   ?? "Paradero #{$hq}";
        }

        // Orden por nombre de usuario y paradero
        asort($controllers, SORT_NATURAL | SORT_FLAG_CASE);
        asort($places, SORT_NATURAL | SORT_FLAG_CASE);

        // Totales por día (PAGO + RETRASO + DEUDA)
        $totalsPerDay = array_fill(1, $this->daysInMonth, 0.0);

        // Construir filas
        $rows = [];
        foreach ($controllers as $uid => $uname) {
            // Solo paraderos que tiene este usuario
            $userHqs = isset($matrix[$uid]) ? array_intersect_key($places, $matrix[$uid]) : [];
            foreach ($userHqs as $hqId => $hqName) {
                foreach (['PAGO','RETRASO','DEUDA'] as $type) {
                    $row = [
                        $uname,
                        $hqName,
                        ucfirst(strtolower($type)), // "Pago", "Retraso", "Deuda"
                    ];

                    $sumRow = 0.0;
                    for ($d = 1; $d <= $this->daysInMonth; $d++) {
                        $val = (float)($matrix[$uid][$hqId][$type][$d] ?? 0.0);
                        $row[] = $val;
                        $sumRow += $val;

                        // total general por día suma los tres tipos
                        $totalsPerDay[$d] += $val;
                    }
                    $row[] = $sumRow;

                    $rows[] = $row;
                }
            }
        }

        // Fila TOTAL GENERAL
        $grand = array_sum($totalsPerDay);
        $footer = ['TOTAL GENERAL', '', ''];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $footer[] = $totalsPerDay[$d];
        }
        $footer[] = $grand;

        $this->rows   = $rows;
        $this->footer = $footer;
    }

    /* ================== HELPERS ================== */

    protected function mesTexto(int $m): string
    {
        return [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ][$m] ?? (string)$m;
    }
}
