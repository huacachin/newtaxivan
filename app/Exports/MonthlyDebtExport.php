<?php

namespace App\Exports;

use App\Models\DebtDay;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

class MonthlyDebtExport implements FromArray, ShouldAutoSize, WithHeadings, WithEvents, WithStyles
{
    public function __construct(
        protected string $monthDate,          // YYYY-MM-DD (cualquier día del mes)
        protected string $search    = '',     // filtro por placa (legacy o vehicle.plate)
        protected string $condition = ''      // '', 'DT','GN','EX','EX5','Exonerado','Amortizado'
    ) {}

    /** cache para AfterSheet */
    private int $rowCount = 0;

    /** datos -> construimos las filas como en tu componente */
    public function array(): array
    {
        [$from, $to] = $this->monthRange($this->monthDate);

        $q = DebtDay::query()->whereBetween('date', [$from, $to]);

        // Filtro por condición (igual que el componente)
        if ($this->condition === 'Exonerado') {
            $q->where('exonerated', '>', 0);
        } elseif ($this->condition === 'Amortizado') {
            $q->where('amortized', '>', 0);
        } elseif (!empty($this->condition)) {
            $q->where('condition', $this->condition);
        }

        // Búsqueda por placa (legacy o vehicle.plate)
        $needle = mb_strtolower(trim($this->search ?? ''));
        if ($needle !== '') {
            $q->where(function ($w) use ($needle) {
                $w->whereRaw('LOWER(legacy_plate) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereExists(function ($sub) use ($needle) {
                        $sub->from('vehicles as v')
                            ->whereColumn('v.id', 'debt_days.vehicle_id')
                            ->whereRaw('LOWER(v.plate) LIKE ?', ['%'.$needle.'%']);
                    });
            });
        }

        $rows = $q->orderBy('date')->get();

        // Mapa de vehículos para COD/PLACA/COND
        $vehicleIds = $rows->pluck('vehicle_id')->filter()->unique()->values();
        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get(['id','sort_order','plate','condition'])
            ->keyBy('id');

        // Build
        $data = [];
        $item = 0;

        $seed     = Carbon::parse($from);
        $daysInMo = $seed->daysInMonth;

        foreach ($rows as $r) {
            $item++;

            $veh      = $r->vehicle_id ? ($vehicles[$r->vehicle_id] ?? null) : null;
            $cod      = $veh->sort_order ?? '';
            $plateStr = $veh ? $veh->plate : ($r->legacy_plate ?? '');
            $cond     = $r->condition ?: ($veh->condition ?? '');

            // Separar días X y X1 (X1 eran “azules” en UI)
            [$daysX, $daysX1] = $this->splitDays($r, $daysInMo);
            $daysX_text  = implode(',', $daysX);
            $daysX1_text = implode(',', $daysX1);

            // Totales
            $total      = (float) ($r->total ?? 0);
            $exonerated = (float) ($r->exonerated ?? 0);
            $amortized  = (float) ($r->amortized ?? 0);
            $toPay      = max(0.0, $total - $exonerated);
            $pending    = max(0.0, $total - $exonerated - $amortized);
            $daysLate   = (int)   ($r->days_late ?? 0);

            $data[] = [
                'item'        => $item,
                'cod'         => $cod,
                'plate'       => $plateStr,
                'condition'   => $cond,
                'days_x'      => $daysX_text,   // "1,2,5,12"
                'days_x1'     => $daysX1_text,  // "3,9,20"
                'days_late'   => $daysLate,     // número de días
                'total'       => $total,        // S/
                'exonerated'  => $exonerated,   // S/
                'to_pay'      => $toPay,        // S/
                'amortized'   => $amortized,    // S/
                'pending'     => $pending,      // S/
            ];
        }

        $this->rowCount = count($data);
        return $data;
    }

    /** encabezados */
    public function headings(): array
    {
        return [
            'Item',
            'Cod',
            'Placa',
            'Condición',
            'Días (X)',
            'Días X1',
            'Días deuda',
            'Total',
            'Exonerado',
            'A pagar',
            'Amortizado',
            'Pendiente',
        ];
    }

    /** header bold */
    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** estilos “bonitos” */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Filas para título y subtítulo
                $ws->insertNewRowBefore(1, 2);
                $headerRow    = 3;  // headings()
                $dataStartRow = 4;
                $lastRow      = $dataStartRow + max(0, $this->rowCount) - 1;
                $lastCol      = 'L'; // A..L

                // Título
                $ws->setCellValue('A1', 'Deuda Mensual por Placa');
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Subtítulo (mes + filtros)
                $seed   = Carbon::parse($this->monthDate)->startOfMonth();
                $monthL = $seed->locale('es')->translatedFormat('F Y');
                $sub = "Mes: {$monthL}";
                if (trim($this->search) !== '')   $sub .= " | Búsqueda: {$this->search}";
                if (trim($this->condition) !== '') $sub .= " | Condición: {$this->condition}";
                $ws->setCellValue('A2', $sub);
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

                // Encabezado con color
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFDBEAFE'); // indigo-100
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $ws->getRowDimension($headerRow)->setRowHeight(22);

                // Freeze pane (debajo del encabezado)
                $ws->freezePane("A{$dataStartRow}");

                // Autofiltro
                if ($lastRow >= $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");
                } else {
                    $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
                }

                // Zebra stripes
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

                // Bordes
                $ws->getStyle("A{$headerRow}:{$lastCol}" . max($headerRow, $lastRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCBD5E1');

                // Anchos de columnas (Item angosto, textos más cómodos)
                $ws->getColumnDimension('A')->setWidth(6);   // Item
                $ws->getColumnDimension('B')->setWidth(8);   // Cod
                $ws->getColumnDimension('C')->setWidth(12);  // Placa
                $ws->getColumnDimension('D')->setWidth(12);  // Condición
                $ws->getColumnDimension('E')->setWidth(22);  // Días (X)
                $ws->getColumnDimension('F')->setWidth(22);  // Días X1
                $ws->getColumnDimension('G')->setWidth(10);  // Días deuda
                $ws->getColumnDimension('H')->setWidth(14);  // Total
                $ws->getColumnDimension('I')->setWidth(14);  // Exonerado
                $ws->getColumnDimension('J')->setWidth(14);  // A pagar
                $ws->getColumnDimension('K')->setWidth(14);  // Amortizado
                $ws->getColumnDimension('L')->setWidth(14);  // Pendiente

                // Alineaciones y formatos
                if ($lastRow >= $dataStartRow) {
                    // Centrados
                    $ws->getStyle("A{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("G{$dataStartRow}:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Moneda S/ para importes
                    foreach (['H','I','J','K','L'] as $col) {
                        $ws->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                        $ws->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Envolver texto en columnas de días
                    $ws->getStyle("E{$dataStartRow}:F{$lastRow}")->getAlignment()->setWrapText(true);
                }

                // Totales al final
                $totalRow = ($lastRow >= $dataStartRow) ? $lastRow + 1 : $headerRow + 1;
                $ws->mergeCells("A{$totalRow}:G{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                if ($lastRow >= $dataStartRow) {
                    // Sumas de importes
                    $ws->setCellValue("H{$totalRow}", "=SUM(H{$dataStartRow}:H{$lastRow})");
                    $ws->setCellValue("I{$totalRow}", "=SUM(I{$dataStartRow}:I{$lastRow})");
                    $ws->setCellValue("J{$totalRow}", "=SUM(J{$dataStartRow}:J{$lastRow})");
                    $ws->setCellValue("K{$totalRow}", "=SUM(K{$dataStartRow}:K{$lastRow})");
                    $ws->setCellValue("L{$totalRow}", "=SUM(L{$dataStartRow}:L{$lastRow})");
                } else {
                    $ws->setCellValue("H{$totalRow}", 0);
                    $ws->setCellValue("I{$totalRow}", 0);
                    $ws->setCellValue("J{$totalRow}", 0);
                    $ws->setCellValue("K{$totalRow}", 0);
                    $ws->setCellValue("L{$totalRow}", 0);
                }
                $ws->getStyle("A{$totalRow}:L{$totalRow}")->getFont()->setBold(true);
                $ws->getStyle("A{$totalRow}:L{$totalRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFCEE7FF');
                foreach (['H','I','J','K','L'] as $col) {
                    $ws->getStyle("{$col}{$totalRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("{$col}{$totalRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $ws->getStyle("A{$totalRow}:G{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }

    /** helpers */
    private function monthRange(string $anyDay): array
    {
        $d1 = Carbon::parse($anyDay ?: now())->startOfMonth();
        $d2 = (clone $d1)->endOfMonth();
        return [$d1->toDateString(), $d2->toDateString()];
    }

    private function splitDays(DebtDay $row, int $daysInMonth): array
    {
        $x  = [];
        $x1 = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col = 'd'.$d;
            $val = (string) ($row->{$col} ?? '');
            if ($val === 'X')  $x[]  = $d;
            if ($val === 'X1') $x1[] = $d;
        }
        return [$x, $x1];
    }
}
