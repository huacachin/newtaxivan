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
            ['CONTRO.', 'PARADERO', 'TIPO'],
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

                // Rango final (letra) SIN castear getHighestColumn()
                $lastRow     = (int) $sheet->getHighestRow();          // incluye la fila de TOTAL GENERAL
                $lastColStr  =        $sheet->getHighestColumn();      // p.ej. "AI"
                $lastColIdx  = Coordinate::columnIndexFromString($lastColStr);
                $endCol      = Coordinate::stringFromColumnIndex($lastColIdx);

                // Insertar título en fila 1
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$endCol}1");
                $sheet->setCellValue('A1', "REPORTE ESTADÍSTICO DE PAGO – {$this->mesTexto($this->month)} {$this->year}");

                // Paletas
                $dark = [
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '23242F']],
                ];
                $titleStyle = [
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']], // gris oscuro
                ];
                $thinBorders = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color'       => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                ];

                // Estilo título (A1)
                $sheet->getStyle('A1')->applyFromArray($titleStyle);
                $sheet->getRowDimension(1)->setRowHeight(24);

                // THEAD (ahora está en fila 2)
                $sheet->getStyle("A2:{$endCol}2")->applyFromArray($dark);
                $sheet->getRowDimension(2)->setRowHeight(20);

                // Congelar encabezados
                $sheet->freezePane('A3');

                // Bordes a toda la tabla (desde encabezado a totales)
                $footerExcelRow = $lastRow + 1; // por la fila de título
                $tableRange = "A2:{$endCol}{$footerExcelRow}";
                $sheet->getStyle($tableRange)->applyFromArray($thinBorders);

                // Pie (última fila: TOTAL GENERAL)
                $sheet->getStyle("A{$footerExcelRow}:{$endCol}{$footerExcelRow}")->applyFromArray($dark);

                // Formato numérico a columnas de días + TOTAL
                $firstDayCol = Coordinate::stringFromColumnIndex(4); // Columna 4 = "D" (1: CONTRO., 2: PARADERO, 3: TIPO)
                $sheet->getStyle("{$firstDayCol}3:{$endCol}{$footerExcelRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // Ancho auto
                for ($i = 1; $i <= $lastColIdx; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                }

                // Resaltar domingos en THEAD (rojo tenue)
                $monthStart = CarbonImmutable::create($this->year, $this->month, 1);
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    if ($monthStart->day($d)->isSunday()) {
                        $col = Coordinate::stringFromColumnIndex(3 + $d); // D es el día 1
                        $sheet->getStyle("{$col}2")->applyFromArray([
                            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EF4444']],
                        ]);
                    }
                }
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
