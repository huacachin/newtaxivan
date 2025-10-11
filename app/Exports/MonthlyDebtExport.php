<?php

namespace App\Exports;

use App\Models\DebtDay;
use App\Models\Vehicle;
use Carbon\Carbon;
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
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class MonthlyDebtExport implements FromArray, ShouldAutoSize, WithHeadings, WithEvents, WithStyles
{
    public function __construct(
        protected string $monthDate,
        protected string $search = '',
        protected string $condition = ''
    ) {}

    private int $rowCount = 0;
    private int $daysInMonth = 30;
    /** Guarda por fila los días X y X1 para pintar RichText */
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

        $rows = $q->orderBy('date')->get();

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

            $veh  = $r->vehicle_id ? ($vehicles[$r->vehicle_id] ?? null) : null;
            $plate = $veh?->plate ?? ($r->legacy_plate ?? '');
            $cond  = $r->condition ?: ($veh->condition ?? '');

            [$x, $x1] = $this->splitDays($r, $this->daysInMonth);
            $this->daysPerRow[$item] = ['x'=>$x, 'x1'=>$x1];

            // Unión ordenada para mostrar; el color se aplica en AfterSheet
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
                'plate'      => $plate,
                'condition'  => $cond,
                'days_mix'   => $daysMixed, // RichText se aplica luego
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
        // 10 columnas (A..J)
        return [
            'Item',
            'Placa',
            'Condición',
            'Días no trabajados (mes)', // X y X1 juntos (X1 en azul)
            'D. deuda',
            'Total',
            'Exonerado',
            'A pagar',
            'Amortizado',
            'Pendiente',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // El header real quedará en la fila 2 (título en la 1)
        return [2 => ['font' => ['bold' => true]]];
    }

    /* ================= ESTILOS / FORMATEO ================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Fuente base 10
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Insertar 1 fila para TÍTULO
                $ws->insertNewRowBefore(1, 1);

                $headerRow    = 2;
                $dataStartRow = 3;
                $lastRow      = $dataStartRow + max(0, $this->rowCount) - 1;
                $lastCol      = 'J'; // A..J

                // ===== TÍTULO (azul #2874A6) =====
                $monthL = \Carbon\Carbon::parse($this->monthDate)->startOfMonth()->locale('es')->translatedFormat('F Y');
                $ws->setCellValue('A1', 'REPORTE DE DEUDA MENSUAL' . ($monthL ? " – {$monthL}" : ''));
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getRowDimension(1)->setRowHeight(18);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2874A6']],
                ]);

                // ===== THEAD (azul) =====
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2874A6']],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(16);

                // Freeze pane
                $ws->freezePane("A{$dataStartRow}");

                // ===== Bordes + zebra =====
                $ws->getStyle("A{$headerRow}:{$lastCol}" . max($headerRow, $lastRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setRGB('CFD8DC');

                if ($lastRow >= $dataStartRow) {
                    $cond = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                    $cond->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
                    $range = "A{$dataStartRow}:{$lastCol}{$lastRow}";
                    $styles = $ws->getStyle($range)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($range)->setConditionalStyles($styles);
                }

                // ===== Anchos ULTRA-compactos (más angosto) =====
                $set = fn($col,$w)=>$ws->getColumnDimension($col)->setWidth($w);
                $set('A',4.2);   // Item
                $set('B',8.0);   // Placa
                $set('C',7.0);   // Condición
                $set('D',13.5);  // Días mixtos
                $set('E',6.0);   // D. deuda
                foreach (['F','G','H','I','J'] as $c) $set($c,9.0); // Montos más angostos

                // Reducir/ajustar sin cortar
                foreach (range('A','J') as $c) {
                    $ws->getStyle("{$c}{$dataStartRow}:{$c}" . max($dataStartRow, $lastRow))
                        ->getAlignment()->setShrinkToFit(true);
                }
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("D{$dataStartRow}:D{$lastRow}")->getAlignment()->setWrapText(true);
                }

                // ===== Todo CENTRADO (incluye montos y pie) =====
                $ws->getStyle("A{$headerRow}:{$lastCol}" . max($headerRow, $lastRow))
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Mantener formato moneda, pero centrado
                if ($lastRow >= $dataStartRow) {
                    foreach (['F','G','H','I','J'] as $c) {
                        $ws->getStyle("{$c}{$dataStartRow}:{$c}{$lastRow}")
                            ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    }
                }

                // ===== Pintado RichText en D (X1 en azul #2874A6) =====
                if ($lastRow >= $dataStartRow) {
                    $blue = '2874A6';
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
                            if (in_array($day, $x1, true)) $run->getFont()->getColor()->setRGB($blue);
                            if ($i < count($union)-1) $rt->createTextRun(',');
                        }
                        $ws->getCell("D{$r}")->setValue($rt);
                    }
                }

                // ===== Footer celeste #CEE7FF (centrado) =====
                $totalRow = ($lastRow >= $dataStartRow) ? $lastRow + 1 : $headerRow + 1;
                $ws->mergeCells("A{$totalRow}:E{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'Total');
                if ($lastRow >= $dataStartRow) {
                    $ws->setCellValue("F{$totalRow}", "=SUM(F{$dataStartRow}:F{$lastRow})");
                    $ws->setCellValue("G{$totalRow}", "=SUM(G{$dataStartRow}:G{$lastRow})");
                    $ws->setCellValue("H{$totalRow}", "=SUM(H{$dataStartRow}:H{$lastRow})");
                    $ws->setCellValue("I{$totalRow}", "=SUM(I{$dataStartRow}:I{$lastRow})");
                    $ws->setCellValue("J{$totalRow}", "=SUM(J{$dataStartRow}:J{$lastRow})");
                } else {
                    foreach (['F','G','H','I','J'] as $c) $ws->setCellValue("{$c}{$totalRow}", 0);
                }

                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CEE7FF']],
                ]);
                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")
                    ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

                // Totales centrados + formato moneda
                foreach (['F','G','H','I','J'] as $c) {
                    $ws->getStyle("{$c}{$totalRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$c}{$totalRow}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
