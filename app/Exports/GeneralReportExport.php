<?php

namespace App\Exports;

use App\Models\Departure;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GeneralReportExport implements FromArray, WithEvents, WithColumnFormatting, WithTitle
{
    /** Colores */
    private const COLOR_TITLE = '2874A6'; // #2874A6
    private const COLOR_FOOT  = 'CEE7FF'; // #2874A6

    /** Topes de ancho para mantener angosto */
    private const CAP_A = 6.0;   // ITEM
    private const CAP_B = 11.0;  // FECHA
    private const CAP_C = 36.0;  // DATOS CLIENTE
    private const CAP_D = 24.0;  // GLOSA
    private const CAP_E = 12.0;  // INGRESO
    private const CAP_F = 12.0;  // EGRESO

    /** Fallback general */
    private const COL_MIN = 5.0;
    private const COL_MAX = 40.0;
    private const PAD     = 1.5;

    public function __construct(
        protected int $year,
        protected int $month
    ) {
        $this->hqNames = collect();
        $this->userMap = collect();
    }

    /** Marcas de estilo */
    protected array $dailyFooterRows = [];
    protected array $dailyFooterData = [];
    protected int $lastRow = 1;
    protected ?int $totalRow = null;
    protected ?int $utilidadRow = null;

    /** Caches */
    protected Collection $hqNames;
    protected Collection $userMap;
    protected array $days = [];

    public function array(): array
    {
        $this->prepareStatic();
        [$payments, $departures, $incomes, $expenses] = $this->loadMonthBatches();

        $monthName = ucfirst(Carbon::create($this->year, $this->month, 1)->locale('es')->translatedFormat('F'));

        $rows = [];
        // Fila 1: Título
        $rows[] = ["Reporte General {$monthName} {$this->year}"];
        // Fila 2: encabezados
        $rows[] = ['Nº','FECHA','DATOS CLIENTE','GLOSA','INGRESO','EGRESO'];

        $item = 1;
        $totalIncomes = 0.0;
        $totalExpenses = 0.0;
        $runningAccum = 0.0;

        foreach ($this->days as $d) {
            $sumI = 0.0; $sumE = 0.0;
            $dateFormatted = Carbon::parse($d)->format('d/m/Y');

            foreach (($payments[$d] ?? collect()) as $p) {
                $cliente = $this->userMap->get($p->user_id, '—') . ' - ' . $dateFormatted;
                $rows[] = [
                    $item++, $d,
                    $cliente,
                    strtoupper($p->type).'-'.$this->hqNames->get($p->headquarter_id, '—'),
                    (float)$p->amount, '0.00',
                ];
                $sumI += (float)$p->amount;
            }

            foreach (($departures[$d] ?? collect()) as $dep) {
                $cliente = $this->userMap->get($dep->user_id, '—') . ' - ' . $dateFormatted;
                $rows[] = [
                    $item++, $d,
                    $cliente,
                    'Salidas-'.$this->hqNames->get($dep->headquarter_id, '—'),
                    (float)$dep->amount, '0.00',
                ];
                $sumI += (float)$dep->amount;
            }

            foreach (($incomes[$d] ?? collect()) as $inc) {
                $cliente = $this->userMap->get($inc->user_id, '—') . ' - ' . $dateFormatted;
                $glosa = trim(($inc->reason ?? '').' - '.($inc->detail ?? ''), ' -');
                $rows[] = [
                    $item++, $d,
                    $cliente,
                    $glosa, (float)$inc->total, '0.00',
                ];
                $sumI += (float)$inc->total;
            }

            foreach (($expenses[$d] ?? collect()) as $exp) {
                $cliente = $this->userMap->get($exp->user_id, '—') . ' - ' . $dateFormatted;
                $glosa = trim(($exp->reason ?? '') . ' - ' . ($exp->detail ?? ''), ' -');
                $rows[] = [
                    $item++, $d,
                    $cliente,
                    $glosa, '0.00', (float)$exp->total,
                ];
                $sumE += (float)$exp->total;
            }

            // Footer del día: SOLO saldo acumulado
            if ($sumI != 0.0 || $sumE != 0.0) {
                $dayBalance = $sumI - $sumE;
                $runningAccum += $dayBalance;

                $rows[] = [
                    'SALDO FINAL-INICIAL  Saldo del día: ' . number_format($dayBalance, 2)
                        . '  Saldo acumulado: ' . number_format($runningAccum, 2), // A (ancla del merge A:D)
                    '', '', '',                               // B, C, D (combinadas con A)
                    (float)$sumI,                             // E
                    (float)$sumE,                             // F
                ];
                $this->dailyFooterRows[] = count($rows);
                $this->dailyFooterData[count($rows)] = [
                    'dia'  => $dayBalance,
                    'acum' => $runningAccum,
                ];
            }

            $totalIncomes  += $sumI;
            $totalExpenses += $sumE;
        }

        // Totales del mes
        $rows[] = ['', '', '', 'TOTAL GENERAL', (float)$totalIncomes, (float)$totalExpenses];
        $this->totalRow = count($rows);

        $rows[] = ['', '', '', 'UTILIDAD', (float)($totalIncomes - $totalExpenses), '0.00'];
        $this->utilidadRow = count($rows);

        $this->lastRow = count($rows);
        return $rows;
    }

    protected function prepareStatic(): void
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $this->days = [];
        for ($d = (clone $start); $d->lte($end); $d->addDay()) {
            $this->days[] = $d->toDateString();
        }

        $this->hqNames = Headquarter::query()->pluck('name', 'id');

        $this->userMap = User::query()
            ->select('id','username')
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => $u->username ?: '—']);
    }

    protected function loadMonthBatches(): array
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        $payments = Payment::query()
            ->selectRaw('DATE(date_register) AS d, type, headquarter_id, user_id, SUM(amount) AS amount')
            ->whereBetween(DB::raw('DATE(date_register)'), [$start, $end])
            ->groupBy('d','type','headquarter_id','user_id')
            ->get()->groupBy('d');

        $departures = Departure::query()
            ->selectRaw('DATE(date) AS d, headquarter_id, user_id, SUM(price) AS amount')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->groupBy('d','headquarter_id','user_id')
            ->get()->groupBy('d');

        $incomes = Income::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total, user_id')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')->get()->groupBy('d');

        $expenses = Expense::query()
            ->selectRaw('id, DATE(date) AS d, reason, detail, total, user_id')
            ->whereBetween(DB::raw('DATE(date)'), [$start, $end])
            ->orderBy('date')->get()->groupBy('d');

        return [$payments, $departures, $incomes, $expenses];
    }

    public function title(): string
    {
        return 'Reporte General';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $blue  = 'FF2874A6';
                $foot  = 'FF' . self::COLOR_FOOT;
                $white = 'FFFFFFFF';
                $black = 'FF000000';
                $gray  = 'FF808080';
                $red   = 'FFF80000';

                // ===== Ocultar cuadrícula =====
                $sheet->setShowGridLines(false);

                // ===== Título (fila 1) — ya viene del array() =====
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(20);

                // ===== Encabezado (fila 2) — azul con bordes blancos internos + outline negro =====
                $sheet->getStyle('A2:F2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $white]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(17);

                // ===== Bordes datos (A3:F{lastRow}) =====
                if ($this->lastRow >= 3) {
                    $sheet->getStyle("A3:F{$this->lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // ===== Colores de texto: Ingreso=azul, Egreso=rojo =====
                if ($this->lastRow >= 3) {
                    $sheet->getStyle("E3:E{$this->lastRow}")->getFont()->getColor()->setARGB('FF0000FF');
                    $sheet->getStyle("F3:F{$this->lastRow}")->getFont()->getColor()->setARGB('FFFF0000');
                }

                // ===== Footers diarios — celeste; A:D combinada con RichText =====
                $blueTxt = 'FF0000FF';
                foreach ($this->dailyFooterRows as $r) {
                    // Combinar A:D en una sola celda con todo el bloque de saldo
                    $sheet->mergeCells("A{$r}:D{$r}");

                    $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $foot]],
                        'font'      => ['bold' => true, 'size' => 10],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    // La celda combinada se alinea a la izquierda y sin wrap
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(false);

                    $dia  = $this->dailyFooterData[$r]['dia']  ?? 0.0;
                    $acum = $this->dailyFooterData[$r]['acum'] ?? 0.0;

                    // RichText: "SALDO " negro + "FINAL-INICIAL" rojo + saldos en azul
                    $rt = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                    $runSaldo = $rt->createTextRun('SALDO ');
                    $runSaldo->getFont()->setBold(true)->getColor()->setARGB($black);
                    $runFinal = $rt->createTextRun('FINAL-INICIAL');
                    $runFinal->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');

                    $runDiaLbl = $rt->createTextRun('   Saldo del día: ');
                    $runDiaLbl->getFont()->setBold(true)->getColor()->setARGB($black);
                    $runDiaVal = $rt->createTextRun(number_format($dia, 2));
                    $runDiaVal->getFont()->setBold(true)->getColor()->setARGB($blueTxt);

                    $runAcumLbl = $rt->createTextRun('   Saldo acumulado: ');
                    $runAcumLbl->getFont()->setBold(true)->getColor()->setARGB($black);
                    $runAcumVal = $rt->createTextRun(number_format($acum, 2));
                    $runAcumVal->getFont()->setBold(true)->getColor()->setARGB($blueTxt);

                    $sheet->getCell("A{$r}")->setValue($rt);
                }

                // ===== Total General + Utilidad =====
                foreach (array_filter([$this->totalRow, $this->utilidadRow]) as $r) {
                    $sheet->getStyle("A{$r}:F{$r}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $foot]],
                        'font'      => ['bold' => true, 'size' => 10],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$r}:D{$r}")->getFont()->getColor()->setARGB($black);
                }

                // ===== Alineaciones =====
                if ($this->lastRow >= 3) {
                    $sheet->getStyle("A3:F{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C3:D{$this->lastRow}")->getAlignment()->setWrapText(true);
                }

                // Re-alinear footers diarios a la izquierda (se sobreescribió arriba)
                foreach ($this->dailyFooterRows as $r) {
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(false);
                }

                // ===== Anchos =====
                $this->capNarrowWidths($sheet);

                // ===== Ocultar columnas vacías (G en adelante) =====
                foreach (range('G', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setVisible(false);
                }
            },
        ];
    }

    private function capNarrowWidths(Worksheet $sheet): void
    {
        $widths = $this->estimateWidths($sheet, 6);

        $caps = [
            1 => self::CAP_A,
            2 => self::CAP_B,
            3 => self::CAP_C,
            4 => self::CAP_D,
            5 => self::CAP_E,
            6 => self::CAP_F,
        ];

        for ($col = 1; $col <= 6; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $final  = min($widths[$col], $caps[$col]);
            $final  = max($final, self::COL_MIN);
            $sheet->getColumnDimension($letter)->setAutoSize(false);
            $sheet->getColumnDimension($letter)->setWidth($final);
        }
    }

    private function estimateWidths(Worksheet $sheet, int $lastColIndex): array
    {
        $widths = array_fill(1, $lastColIndex, self::COL_MIN);
        for ($col = 1; $col <= $lastColIndex; $col++) {
            for ($row = 1; $row <= $this->lastRow; $row++) {
                $val = $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                if ($val === null) continue;
                $len = function_exists('mb_strwidth') ? mb_strwidth((string)$val, 'UTF-8') : strlen((string)$val);
                $factor = in_array($col, [1,2,5,6], true) ? 1.0 : 0.9;
                $est = max(self::COL_MIN, min(self::COL_MAX, $len * $factor + self::PAD));
                if ($est > $widths[$col]) $widths[$col] = $est;
            }
        }
        // mínimos prácticos
        $widths[1] = max($widths[1], 6.0);
        $widths[2] = max($widths[2], 10.0);
        $widths[3] = max($widths[3], 14.0);
        $widths[4] = max($widths[4], 18.0);
        $widths[5] = max($widths[5], 11.0);
        $widths[6] = max($widths[6], 11.0);

        return $widths;
    }

    public function columnFormats(): array
    {
        return [
            'E' => '#,##0.00',
            'F' => '#,##0.00',
        ];
    }
}
