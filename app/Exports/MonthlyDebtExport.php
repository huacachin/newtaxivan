<?php

namespace App\Exports;

use App\Models\DebtDay;
use App\Models\Vehicle;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyDebtExport implements FromArray, WithHeadings, WithEvents, WithStyles, WithTitle
{
    public function __construct(
        protected string $monthDate,
        protected string $search = '',
        protected string $condition = ''
    ) {}

    private int $rowCount = 0;
    private int $daysInMonth = 30;
    private array $daysPerRow = []; // [rowIndex => ['x'=>[...], 'x1'=>[...]]]

    /* ================= DATA ================= */
    public function array(): array
    {
        [$from, $to] = $this->monthRange($this->monthDate);

        $q = DebtDay::query()->whereBetween('date', [$from, $to]);

        if ($this->condition === 'Exonerado') $q->where('exonerated', '>', 0);
        elseif ($this->condition === 'Amortizado') $q->where('amortized', '>', 0);
        elseif ($this->condition !== '') $q->where('condition', $this->condition);

        $needle = mb_strtolower(trim($this->search ?? ''));
        if ($needle !== '') {
            $q->where(function ($w) use ($needle) {
                $w->whereRaw('LOWER(legacy_plate) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereExists(function ($sub) use ($needle) {
                        $sub->from('vehicles as v')
                            ->whereColumn('v.id','debt_days.vehicle_id')
                            ->whereRaw('LOWER(v.plate) LIKE ?', ['%'.$needle.'%']);
                    });
            });
        }

        $rows = $q->orderBy('id')->get();

        $vehicleIds = $rows->pluck('vehicle_id')->filter()->unique()->values();
        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get(['id','plate','condition'])
            ->keyBy('id');

        $seed = Carbon::parse($from);
        $this->daysInMonth = $seed->daysInMonth;

        $data = [];
        $item = 0;

        foreach ($rows as $r) {
            $item++;

            $veh   = $r->vehicle_id ? ($vehicles[$r->vehicle_id] ?? null) : null;
            $plate = $veh?->plate ?? ($r->legacy_plate ?? '');
            $cond  = $r->condition ?: ($veh->condition ?? '');

            [$x, $x1] = $this->splitDays($r, $this->daysInMonth);
            $this->daysPerRow[$item] = ['x'=>$x, 'x1'=>$x1];

            $union = array_values(array_unique(array_merge($x, $x1)));
            sort($union);
            $daysMixed = implode(',', $union);

            $total      = (float) ($r->total ?? 0);
            $exonerated = (float) ($r->exonerated ?? 0);
            $amortized  = (float) ($r->amortized ?? 0);
            $toPay      = max(0.0, $total - $exonerated);
            $pending    = max(0.0, $total - $exonerated - $amortized);
            $daysLate   = (int)   ($r->days_late ?? 0);

            $data[] = [
                'item'       => $item,
                'cod'        => $veh?->sort_order ?? '',
                'plate'      => $plate,
                'condition'  => $cond,
                'days_mix'   => $daysMixed, // RichText después
                'days_late'  => $daysLate,
                'total'      => $total,
                'exonerated' => $exonerated,
                'to_pay'     => $toPay,
                'amortized'  => $amortized,
                'pending'    => $pending,
            ];
        }

        $this->rowCount = count($data);
        return $data;
    }

    public function headings(): array
    {
        $monthL = mb_strtoupper(\Carbon\Carbon::parse($this->monthDate)->startOfMonth()->locale('es')->translatedFormat('F'));
        return [
            'Nº',
            'Cod',
            'Placa',
            'Cond.',
            "DIAS NO TRABAJADOS ({$monthL})",
            'D. deuda',
            'Total',
            'Exon.',
            'A pagar',
            'Amort.',
            'Pend.',
        ];
    }


    public function title(): string
    {
        return 'Deuda Mensual';
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    /* ================= ESTILOS / FORMATEO ================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $gray     = 'FF808080';
                $red      = 'FFF80000';

                $ws->getDefaultRowDimension()->setRowHeight(14);

                // ===== Insertar fila de título =====
                $ws->insertNewRowBefore(1, 1);

                $monthLabel = \Carbon\Carbon::parse($this->monthDate)->startOfMonth()->locale('es')->translatedFormat('F Y');
                $title = 'DEUDA MENSUAL - ' . mb_strtoupper($monthLabel);
                $lastCol = 'K';
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', $title);
                $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                // ===== Ocultar cuadrícula =====
                $ws->setShowGridLines(false);

                // ===== Encabezado (fila 2) =====
                $headerRow    = 2;
                $dataStartRow = 3;
                $lastRow      = $dataStartRow + max(0, $this->rowCount) - 1;

                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $white]],
                        'outline'    => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(17);

                // RichText en D2: "DIAS NO TRABAJADOS" blanco + "(MES)" en #eab723
                $monthName = mb_strtoupper(\Carbon\Carbon::parse($this->monthDate)->startOfMonth()->locale('es')->translatedFormat('F'));
                $rtH = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $r1 = $rtH->createTextRun('DIAS NO TRABAJADOS');
                $r1->getFont()->setBold(true)->setSize(10);
                $r1->getFont()->getColor()->setRGB('FFFFFF');
                $r2 = $rtH->createTextRun('(');
                $r2->getFont()->setBold(true)->setSize(10);
                $r2->getFont()->getColor()->setRGB('FFFFFF');
                $r3 = $rtH->createTextRun($monthName);
                $r3->getFont()->setBold(true)->setSize(10);
                $r3->getFont()->getColor()->setRGB('EAB723');
                $r4 = $rtH->createTextRun(')');
                $r4->getFont()->setBold(true)->setSize(10);
                $r4->getFont()->getColor()->setRGB('FFFFFF');
                $ws->getCell("E{$headerRow}")->setValue($rtH);

                // ===== Bordes datos =====
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    ]);

                    // Alineaciones
                    $ws->getStyle("A{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("E{$dataStartRow}:E{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("F{$dataStartRow}:K{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    /* Rellenar vacíos con 0 en montos (G..K) */
                    foreach (range('G','K') as $col) {
                        for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                            $cell = $ws->getCell("{$col}{$r}");
                            if ($cell->getValue() === null || $cell->getValue() === '') $cell->setValue(0);
                        }
                    }

                    // Formato moneda
                    foreach (['G','H','I','J','K'] as $c) {
                        $ws->getStyle("{$c}{$dataStartRow}:{$c}{$lastRow}")
                            ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    }

                    // Columna Exon. (H) en rojo claro
                    $ws->getStyle("H{$dataStartRow}:H{$lastRow}")
                        ->getFont()->getColor()->setRGB('FF6666');

                    // RichText en E (días X1 en azul oscuro + negrita)
                    $darkBlue = '0066FF';
                    for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                        $item = (int) $ws->getCell("A{$r}")->getCalculatedValue();
                        $sets = $this->daysPerRow[$item] ?? null;
                        if (!$sets) continue;

                        $x  = array_map('intval', $sets['x']);
                        $x1 = array_map('intval', $sets['x1']);
                        $union = array_values(array_unique(array_merge($x, $x1)));
                        sort($union);

                        $rt = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                        foreach ($union as $i => $day) {
                            $run = $rt->createTextRun((string)$day);
                            if (in_array($day, $x1, true)) {
                                $run->getFont()->getColor()->setRGB($darkBlue);
                                $run->getFont()->setBold(true);
                            }
                            if ($i < count($union) - 1) $rt->createTextRun(',');
                        }
                        $ws->getCell("E{$r}")->setValue($rt);
                    }
                }

                // ===== Fila TOTAL =====
                $totalRow = ($lastRow >= $dataStartRow) ? $lastRow + 1 : $dataStartRow;
                $ws->mergeCells("A{$totalRow}:F{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');

                if ($lastRow >= $dataStartRow) {
                    foreach (['G','H','I','J','K'] as $c) {
                        $ws->setCellValue("{$c}{$totalRow}", "=SUM({$c}{$dataStartRow}:{$c}{$lastRow})");
                    }
                } else {
                    foreach (['G','H','I','J','K'] as $c) $ws->setCellValue("{$c}{$totalRow}", 0);
                }

                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                foreach (['G','H','I','J','K'] as $c) {
                    $ws->getStyle("{$c}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$c}{$totalRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }
                $ws->getRowDimension($totalRow)->setRowHeight(17);

                // ===== Anchos de columna =====
                $ws->getColumnDimension('A')->setWidth(4.2);
                $ws->getColumnDimension('B')->setWidth(4.5);
                $ws->getColumnDimension('C')->setWidth(10.0);
                $ws->getColumnDimension('D')->setWidth(5.0);
                $ws->getColumnDimension('E')->setWidth(26.0);
                $ws->getColumnDimension('F')->setWidth(6.5);
                foreach (['G','H','I','J','K'] as $c) $ws->getColumnDimension($c)->setWidth(10.8);

                // ===== Ocultar columnas vacías (L en adelante) =====
                foreach (range('L', 'Z') as $col) {
                    $ws->getColumnDimension($col)->setVisible(false);
                }

                // ===== Fuente 10 global =====
                $finalRow = (int) $ws->getHighestRow();
                $ws->getStyle("A1:{$lastCol}{$finalRow}")->getFont()->setSize(10);
                $ws->getStyle("A1:{$lastCol}{$finalRow}")->getAlignment()->setWrapText(false);
                // Restaurar wrap en D para datos
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("E{$dataStartRow}:E{$lastRow}")->getAlignment()->setWrapText(true);
                }
            },
        ];
    }

    /* ================= HELPERS ================= */
    private function monthRange(string $anyDay): array
    {
        $d1 = Carbon::parse($anyDay ?: now())->startOfMonth();
        $d2 = (clone $d1)->endOfMonth();
        return [$d1->toDateString(), $d2->toDateString()];
    }

    private function splitDays(DebtDay $row, int $daysInMonth): array
    {
        $x = []; $x1 = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = 'd'.$d;
            $val = (string) ($row->{$col} ?? '');
            if ($val === 'X')  $x[]  = $d;
            if ($val === 'X1') $x1[] = $d;
        }
        return [$x, $x1];
    }
}
