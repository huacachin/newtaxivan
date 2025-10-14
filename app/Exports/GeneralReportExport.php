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
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Font as SharedFont;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GeneralReportExport implements FromArray, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    /** Colores */
    private const COLOR_TITLE = '2874A6'; // #2874A6
    private const COLOR_FOOT  = 'CEE7FF'; // #CEE7FF

    /** Topes de ancho para mantener angosto */
    private const CAP_A = 6.0;   // ITEM
    private const CAP_B = 11.0;  // FECHA
    private const CAP_C = 16.0;  // DATOS CLIENTE
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

        $monthName = Carbon::create($this->year, $this->month, 1)->locale('es')->translatedFormat('F');

        $rows = [];
        // Fila 1: Título
        $rows[] = ["Reporte General {$monthName} {$this->year}"];
        // Fila 2: encabezados
        $rows[] = ['ITEM','FECHA','DATOS CLIENTE','GLOSA','INGRESO','EGRESO'];

        $item = 1;
        $totalIncomes = 0.0;
        $totalExpenses = 0.0;
        $runningAccum = 0.0;

        foreach ($this->days as $d) {
            $sumI = 0.0; $sumE = 0.0;

            foreach (($payments[$d] ?? collect()) as $p) {
                $rows[] = [
                    $item++, $d,
                    $this->userMap->get($p->user_id, '—'),
                    strtoupper($p->type).'-'.$this->hqNames->get($p->headquarter_id, '—'),
                    (float)$p->amount, 0.0,
                ];
                $sumI += (float)$p->amount;
            }

            foreach (($departures[$d] ?? collect()) as $dep) {
                $rows[] = [
                    $item++, $d,
                    $this->userMap->get($dep->user_id, '—'),
                    'Salidas-'.$this->hqNames->get($dep->headquarter_id, '—'),
                    (float)$dep->amount, 0.0,
                ];
                $sumI += (float)$dep->amount;
            }

            foreach (($incomes[$d] ?? collect()) as $inc) {
                $glosa = trim(($inc->reason ?? '').' - '.($inc->detail ?? ''), ' -');
                $rows[] = [
                    $item++, $d,
                    $this->userMap->get($inc->user_id, '—'),
                    $glosa, (float)$inc->total, 0.0,
                ];
                $sumI += (float)$inc->total;
            }

            foreach (($expenses[$d] ?? collect()) as $exp) {
                $glosa = trim(($exp->reason ?? '') . ' - ' . ($exp->detail ?? ''), ' -');
                $rows[] = [
                    $item++, $d,
                    $this->userMap->get($exp->user_id, '—'),
                    $glosa, 0.0, (float)$exp->total,
                ];
                $sumE += (float)$exp->total;
            }

            // Footer del día: SOLO saldo acumulado
            if ($sumI != 0.0 || $sumE != 0.0) {
                $dayBalance = $sumI - $sumE;
                $runningAccum += $dayBalance;

                $rows[] = [
                    '', '',                                   // ITEM, FECHA
                    'SALDO FINAL–INICIAL',                    // C (etiqueta)
                    'Saldo acumulado: ' . number_format($runningAccum, 2), // D (valor)
                    (float)$sumI,                             // E
                    (float)$sumE,                             // F
                ];
                $this->dailyFooterRows[] = count($rows);
            }

            $totalIncomes  += $sumI;
            $totalExpenses += $sumE;
        }

        // Totales del mes
        $rows[] = ['', '', '', 'TOTAL GENERAL', (float)$totalIncomes, (float)$totalExpenses];
        $this->totalRow = count($rows);

        $rows[] = ['', '', '', 'UTILIDAD', (float)($totalIncomes - $totalExpenses), ''];
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
            ->select('id','name','document_type','document_number')
            ->get()
            ->mapWithKeys(function ($u) {
                $doc = trim(($u->document_type ?? '').' '.($u->document_number ?? ''));
                $label = trim($u->name . ($doc ? " · $doc" : ''));
                return [$u->id => ($label !== '' ? $label : '—')];
            });
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                // AutoSize exacto (si está disponible)
                if (method_exists(SharedFont::class, 'setAutoSizeMethod')) {
                    SharedFont::setAutoSizeMethod(SharedFont::AUTOSIZE_METHOD_EXACT);
                }

                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                // Título (A1:F1)
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Encabezado (fila 2) — #2874A6
                $sheet->getStyle('A2:F2')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::COLOR_TITLE);
                $sheet->getStyle('A2:F2')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A2:F2')->getFont()->setBold(true);
                $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Congelar desde fila 3
                $sheet->freezePane('A3');

                // Footers diarios — #CEE7FF
                foreach ($this->dailyFooterRows as $r) {
                    $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::COLOR_FOOT);
                    $sheet->getStyle("A{$r}:F{$r}")->getFont()->setBold(true);
                }

                // Totales del mes — #CEE7FF
                if ($this->totalRow) {
                    $sheet->getStyle("A{$this->totalRow}:F{$this->totalRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::COLOR_FOOT);
                    $sheet->getStyle("A{$this->totalRow}:F{$this->totalRow}")->getFont()->setBold(true);
                }
                if ($this->utilidadRow) {
                    $sheet->getStyle("A{$this->utilidadRow}:F{$this->utilidadRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::COLOR_FOOT);
                    $sheet->getStyle("A{$this->utilidadRow}:F{$this->utilidadRow}")->getFont()->setBold(true);
                }

                // Bordes
                $sheet->getStyle("A1:F{$this->lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Alineaciones
                $sheet->getStyle("E3:F{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("C3:D{$this->lastRow}")->getAlignment()->setWrapText(true);

                // AutoSize estándar
                $highestColumn = $sheet->getHighestColumn();
                $lastColIndex  = Coordinate::columnIndexFromString($highestColumn);
                for ($col = 1; $col <= $lastColIndex; $col++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
                }

                // Fallback con topes para mantener angosto
                $this->capNarrowWidths($sheet);
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
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
