<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class GeneralReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithEvents, WithStyles
{
    public function __construct(
        protected string $month // "YYYY-MM"
    ) {}

    /** caches para styling */
    private int $rowCount = 0;

    /** columnas detectadas dinámicamente */
    private ?string $paymentsAmountCol = null;
    private ?string $departuresSumExpr = null;

    /** ===== Datos ===== */
    public function array(): array
    {
        // Rango del mes
        [$mStart, $mEnd] = $this->monthRange("{$this->month}-01");
        $weeks = $this->computeWeeks($mStart, $mEnd);

        // Mapas (usuarios y sedes)
        $userMap = $this->buildUserMap();
        $hqMap   = $this->buildHqMap();

        // Detectar columnas dinámicas
        $this->paymentsAmountCol = $this->detectPaymentsAmountCol();
        $this->departuresSumExpr = $this->detectDeparturesSumExpr();

        $hasPayments   = Schema::hasTable('payments');
        $hasDepartures = Schema::hasTable('departures') && $this->departuresSumExpr !== null;
        $hasIncomes    = Schema::hasTable('incomes');
        $hasExpenses   = Schema::hasTable('expenses');

        $data = [];

        $grandIncome = 0.0;
        $grandExpense = 0.0;
        $grandProfit = 0.0;

        foreach ($weeks as $wk) {
            $wStart = $wk['start'];
            $wEnd   = $wk['end'];
            $label  = $wk['label'];

            // Encabezado de semana (se estiliza en AfterSheet)
            $data[] = ["__WEEK__:{$label}", null, null, null, null, null, null];

            $rows = [];

            // PAGOS
            if ($hasPayments && $this->paymentsAmountCol) {
                $rows = array_merge($rows,
                    DB::table('payments as p')
                        ->whereBetween('p.date_register', [$wStart, $wEnd])
                        ->select('p.date_register as date', 'p.user_id', 'p.headquarter_id', 'p.type')
                        ->selectRaw('SUM(p.' . $this->paymentsAmountCol . ') as total')
                        ->groupBy('p.date_register','p.user_id','p.headquarter_id','p.type')
                        ->get()
                        ->map(function ($r) use ($userMap, $hqMap) {
                            $user = $userMap[$r->user_id] ?? '-';
                            $hq   = $hqMap[$r->headquarter_id] ?? ($r->headquarter_id ? ('HQ#'.$r->headquarter_id) : '-');
                            return [
                                'date'    => (string)$r->date,
                                'user'    => $user,
                                'source'  => 'Pago' . ($r->type ? " ({$r->type})" : ''),
                                'detail'  => $hq,
                                'income'  => (float)$r->total,
                                'expense' => 0.0,
                            ];
                        })->all()
                );
            }

            // DEPARTURES
            if ($hasDepartures) {
                $expr = $this->departuresSumExpr; // SUM(price) / SUM(amount) / SUM(COALESCE(price,amount))
                $rows = array_merge($rows,
                    DB::table('departures as d')
                        ->whereBetween('d.date', [$wStart, $wEnd])
                        ->select('d.date','d.user_id','d.headquarter_id')
                        ->selectRaw("$expr as total")
                        ->groupBy('d.date','d.user_id','d.headquarter_id')
                        ->get()
                        ->map(function ($r) use ($userMap, $hqMap) {
                            $user = $userMap[$r->user_id] ?? '-';
                            $hq   = $hqMap[$r->headquarter_id] ?? ($r->headquarter_id ? ('HQ#'.$r->headquarter_id) : '-');
                            return [
                                'date'    => (string)$r->date,
                                'user'    => $user,
                                'source'  => 'Salida',
                                'detail'  => $hq,
                                'income'  => (float)$r->total,
                                'expense' => 0.0,
                            ];
                        })->all()
                );
            }

            // INCOMES
            if ($hasIncomes) {
                $rows = array_merge($rows,
                    DB::table('incomes as i')
                        ->whereBetween('i.date', [$wStart, $wEnd])
                        ->select('i.date','i.user_id','i.reason','i.detail','i.total')
                        ->orderBy('i.date')
                        ->get()
                        ->map(function ($r) use ($userMap) {
                            $user = $userMap[$r->user_id] ?? '-';
                            $glosa = trim(implode(' - ', array_filter([(string)$r->reason, (string)$r->detail])));
                            return [
                                'date'    => (string)$r->date,
                                'user'    => $user,
                                'source'  => 'Ingreso',
                                'detail'  => $glosa !== '' ? $glosa : 'Ingreso',
                                'income'  => (float)$r->total,
                                'expense' => 0.0,
                            ];
                        })->all()
                );
            }

            // EXPENSES
            if ($hasExpenses) {
                $rows = array_merge($rows,
                    DB::table('expenses as e')
                        ->whereBetween('e.date', [$wStart, $wEnd])
                        ->select('e.date','e.user_id','e.reason','e.detail','e.total','e.document_type','e.in_charge')
                        ->orderBy('e.date')
                        ->get()
                        ->map(function ($r) use ($userMap) {
                            $user  = $userMap[$r->user_id] ?? '-';
                            $parts = [(string)$r->reason, (string)$r->detail];
                            if (!empty($r->document_type)) $parts[] = 'Doc: '.$r->document_type;
                            if (!empty($r->in_charge))     $parts[] = 'Resp: '.$r->in_charge;
                            $glosa = trim(implode(' - ', array_filter($parts)));
                            return [
                                'date'    => (string)$r->date,
                                'user'    => $user,
                                'source'  => 'Gasto',
                                'detail'  => $glosa !== '' ? $glosa : 'Gasto',
                                'income'  => 0.0,
                                'expense' => (float)$r->total,
                            ];
                        })->all()
                );
            }

            // Orden: por fecha y origen
            usort($rows, function($a,$b){
                if ($a['date'] === $b['date']) return $a['source'] <=> $b['source'];
                return $a['date'] <=> $b['date'];
            });

            // Volcar filas + subtotal semana
            $wIncome = 0.0; $wExpense = 0.0; $wProfit = 0.0;

            foreach ($rows as $rr) {
                $profit = (float)$rr['income'] - (float)$rr['expense'];
                $data[] = [
                    $rr['date'],
                    $rr['user'],
                    $rr['source'],
                    $rr['detail'],
                    (float)$rr['income'],
                    (float)$rr['expense'],
                    $profit,
                ];
                $wIncome  += (float)$rr['income'];
                $wExpense += (float)$rr['expense'];
                $wProfit  += $profit;
            }

            $data[] = ["__SUB__:Subtotal {$label}", null, null, null, $wIncome, $wExpense, $wProfit];

            $grandIncome  += $wIncome;
            $grandExpense += $wExpense;
            $grandProfit  += $wProfit;
        }

        // TOTAL MES
        $data[] = ["__TOT__:TOTAL MES", null, null, null, $grandIncome, $grandExpense, $grandProfit];

        $this->rowCount = count($data);
        return $data;
    }

    /** ===== Headings (se insertan tras Título/Subtítulo) ===== */
    public function headings(): array
    {
        return ['Fecha','Usuario','Origen','Detalle','Ingreso','Gasto','Utilidad'];
    }

    /** Header bold */
    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** ===== Estilos bonitos ===== */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertar Título + Subtítulo arriba
                $ws->insertNewRowBefore(1, 2);
                $headerRow    = 3;  // headings()
                $dataStartRow = 4;
                $lastRow      = $dataStartRow + max(0, $this->rowCount) - 1;
                $lastCol      = 'G'; // A..G

                // Título
                $title = 'Reporte General de Caja';
                $ws->setCellValue('A1', $title);
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Subtítulo (mes y fecha de generación)
                $seed   = Carbon::createFromFormat('Y-m-d', "{$this->month}-01");
                $monthL = $seed->locale('es')->translatedFormat('F Y');
                $ws->setCellValue('A2', "Mes: {$monthL} | Generado: ".now()->format('Y-m-d H:i'));
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

                // Estilo encabezado columnas
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFDBEAFE');
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $ws->getRowDimension($headerRow)->setRowHeight(22);

                // Freeze pane
                $ws->freezePane("A{$dataStartRow}");

                // Autofiltro
                if ($lastRow >= $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");
                } else {
                    $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
                }

                // Zebra stripes (sobre el rango de datos)
                if ($lastRow >= $dataStartRow) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF8FAFC');
                    $rangeData = "A{$dataStartRow}:{$lastCol}{$lastRow}";
                    $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($rangeData)->setConditionalStyles($styles);
                }

                // Bordes finos
                $ws->getStyle("A{$headerRow}:{$lastCol}" . max($headerRow, $lastRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCBD5E1');

                // Anchos
                $ws->getColumnDimension('A')->setWidth(12); // Fecha
                $ws->getColumnDimension('B')->setWidth(18); // Usuario
                $ws->getColumnDimension('C')->setWidth(14); // Origen
                $ws->getColumnDimension('D')->setWidth(42); // Detalle
                $ws->getColumnDimension('E')->setWidth(16); // Ingreso
                $ws->getColumnDimension('F')->setWidth(16); // Gasto
                $ws->getColumnDimension('G')->setWidth(16); // Utilidad

                // Formatos numéricos y alineaciones
                if ($lastRow >= $dataStartRow) {
                    foreach (['E','F','G'] as $col) {
                        $ws->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                        $ws->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                // ---- Estilizar filas especiales (semana / subtotal / total) ----
                for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                    $a = (string) $ws->getCell("A{$r}")->getValue();

                    if (str_starts_with($a, '__WEEK__:')) {
                        // Encabezado de semana
                        $label = substr($a, 9);
                        $ws->setCellValue("A{$r}", $label);
                        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFEFF6FF'); // azul muy claro
                        $ws->getStyle("A{$r}")
                            ->getFont()->setBold(true);
                        $ws->getStyle("A{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        continue;
                    }

                    if (str_starts_with($a, '__SUB__:')) {
                        // Subtotal semana
                        $label = substr($a, 8);
                        $ws->setCellValue("A{$r}", $label);
                        $ws->mergeCells("A{$r}:D{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFont()->setBold(true);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF1F5F9'); // slate-100
                        $ws->getStyle("A{$r}:D{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        // moneda en E..G ya formateada por arriba
                        continue;
                    }

                    if (str_starts_with($a, '__TOT__:')) {
                        // TOTAL MES
                        $label = substr($a, 8);
                        $ws->setCellValue("A{$r}", $label);
                        $ws->mergeCells("A{$r}:D{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFont()->setBold(true);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFCEE7FF'); // celeste
                        $ws->getStyle("A{$r}:D{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        continue;
                    }

                    // Fila normal: centrar fecha/origen/usuario, detalle left
                    $ws->getStyle("A{$r}:C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
            },
        ];
    }

    /* ================= Helpers ================= */

    private function monthRange(string $anyDay): array
    {
        $d1 = Carbon::createFromFormat('Y-m-d', $anyDay)->startOfMonth();
        $d2 = (clone $d1)->endOfMonth();
        return [$d1->toDateString(), $d2->toDateString()];
    }

    private function computeWeeks(string $mStart, string $mEnd): array
    {
        $weeks = [];
        $cursor = Carbon::createFromFormat('Y-m-d', $mStart)->startOfWeek(Carbon::MONDAY);
        $monthStart = Carbon::createFromFormat('Y-m-d', $mStart);
        $monthEnd   = Carbon::createFromFormat('Y-m-d', $mEnd);
        $idx = 1;

        while ($cursor <= $monthEnd) {
            $wStart = (clone $cursor);
            $wEnd   = (clone $cursor)->endOfWeek(Carbon::SUNDAY);

            if ($wEnd < $monthStart) { $cursor = $cursor->addWeek(); continue; }

            $rangeStart = $wStart->greaterThan($monthStart) ? $wStart : $monthStart;
            $rangeEnd   = $wEnd->lessThan($monthEnd) ? $wEnd : $monthEnd;

            $weeks[] = [
                'i'     => $idx,
                'start' => $rangeStart->toDateString(),
                'end'   => $rangeEnd->toDateString(),
                'label' => sprintf('Semana %d (%s–%s)', $idx, $rangeStart->format('d'), $rangeEnd->format('d')),
            ];

            $idx++;
            $cursor = $cursor->addWeek();
        }
        return $weeks;
    }

    private function buildUserMap(): array
    {
        $map = [];
        if (Schema::hasTable('users')) {
            DB::table('users')->select('id','name')->orderBy('id')->chunk(1000, function($rows) use (&$map){
                foreach ($rows as $r) $map[(int)$r->id] = (string)$r->name;
            });
        }
        return $map;
    }

    private function buildHqMap(): array
    {
        $map = [];
        if (Schema::hasTable('headquarters')) {
            DB::table('headquarters')->select('id','name')->orderBy('id')->chunk(1000, function($rows) use (&$map){
                foreach ($rows as $r) $map[(int)$r->id] = (string)$r->name;
            });
        }
        return $map;
    }

    private function detectPaymentsAmountCol(): ?string
    {
        if (!Schema::hasTable('payments')) return null;
        $candidates = ['amount','total','total_amount','importe','price','value','amount_total'];
        $cols = Schema::getColumnListing('payments');
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }

    private function detectDeparturesSumExpr(): ?string
    {
        if (!Schema::hasTable('departures')) return null;
        $hasPrice  = Schema::hasColumn('departures', 'price');
        $hasAmount = Schema::hasColumn('departures', 'amount');
        if ($hasPrice && $hasAmount) return 'SUM(COALESCE(price, amount))';
        if ($hasPrice)               return 'SUM(price)';
        if ($hasAmount)              return 'SUM(amount)';
        return null;
    }
}
