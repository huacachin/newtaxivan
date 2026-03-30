<?php

namespace App\Exports;

use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DriversReportExport implements FromArray, WithColumnFormatting, WithEvents
{
    use \App\Traits\CompactColumnWidths;

    public function __construct(
        protected ?string $search = null,
        protected ?string $filter = 'plate' // plate | name | code
    ) {}

    private int $rowNumActive = 0;
    private int $rowNumFree   = 0;
    private int $totalActive  = 0;
    private int $totalFree    = 0;

    protected function headings(): array
    {
        return [
            'Item', 'Cod', 'Placa', 'Nombre', 'N° Documento',
            'I.Contrato', 'F.Contrato', 'Teléfono', 'Condición',
        ];
    }

    public function array(): array
    {
        [$active, $free] = $this->fetchData();

        $this->totalActive = $active->count();
        $this->totalFree   = $free->count();

        $head = $this->headings();
        $rows = [];

        // === Tabla 1: Conductores con vehículo activo ===
        $rows[] = $head;
        foreach ($active as $d) {
            $plates = $d->relationLoaded('vehicles')
                ? $d->vehicles
                    ->sortBy([
                        fn($a, $b) => ($a->sort_order === null ? 1 : 0) <=> ($b->sort_order === null ? 1 : 0),
                        ['sort_order', 'asc'],
                        ['plate', 'asc'],
                    ])
                    ->pluck('plate')->filter()->unique()->values()->implode(', ')
                : '';

            $cod = $d->sort_order_min ?? $d->id;

            $rows[] = [
                ++$this->rowNumActive,                          // Item
                $cod,                                           // Cod
                $plates ?: '',                                  // Placa
                (string)$d->name,                               // Nombre
                (string)$d->document_number,                    // N° Documento
                optional($d->contract_start)?->format('d/m/Y') ?: null, // I.Contrato
                optional($d->contract_end)?->format('d/m/Y') ?: null,   // F.Contrato
                (string)$d->phone,                              // Teléfono
                (string)$d->condition,                          // Condición
            ];
        }

        // === Footer sección 1 (placeholder) ===
        $rows[] = array_fill(0, 9, '');

        // === Separador + Título sección 2 (placeholder) ===
        $rows[] = array_fill(0, 9, '');
        $rows[] = array_fill(0, 9, '');

        // === Tabla 2: Conductores libres ===
        $rows[] = $head;
        foreach ($free as $d) {
            $rows[] = [
                ++$this->rowNumFree,                            // Item
                '—',                                            // Cod
                '—',                                            // Placa
                (string)$d->name,
                (string)$d->document_number,
                optional($d->contract_start)?->format('d/m/Y') ?: null,
                optional($d->contract_end)?->format('d/m/Y') ?: null,
                (string)$d->phone,
                (string)$d->condition,
            ];
        }

        // === Footer sección 2 (placeholder) ===
        $rows[] = array_fill(0, 9, '');

        return $rows;
    }


    public function columnFormats(): array
    {
        return [
            'E' => '@',
            'F' => 'dd/mm/yyyy',
            'G' => 'dd/mm/yyyy',
            'H' => '@',
        ];
    }


    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $e->sheet->getDelegate()->setTitle('Conductores');
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $gray     = 'FF808080';
                $red      = 'FFF80000';

                $ws->getDefaultRowDimension()->setRowHeight(15);

                // ===== Insertar fila de título =====
                $ws->insertNewRowBefore(1, 1);
                $lastCol = 'I';

                $total = $this->totalActive + $this->totalFree;
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', "REPORTE DE CONDUCTORES (Total: {$total})");
                $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                // ===== Ocultar cuadrícula =====
                $ws->setShowGridLines(false);

                // ===== Posiciones calculadas =====
                // After title insert at row 1:
                // Row 2: header1
                // Rows 3 to 2+totalActive: active data
                // Row 3+totalActive: TOTAL ACTIVOS (placeholder)
                // Row 4+totalActive: gap
                // Row 5+totalActive: CONDUCTORES LIBRES title (placeholder)
                // Row 6+totalActive: header2
                // Rows 7+totalActive to 6+totalActive+totalFree: free data
                // Row 7+totalActive+totalFree: TOTAL LIBRES (placeholder)

                $header1    = 2;
                $dataStart1 = 3;
                $dataEnd1   = 2 + $this->totalActive;
                $foot1      = $dataEnd1 + 1;
                $gapRow     = $foot1 + 1;
                $title2     = $gapRow + 1;
                $header2    = $title2 + 1;
                $dataStart2 = $header2 + 1;
                $dataEnd2   = $header2 + $this->totalFree;
                $foot2      = $dataEnd2 + 1;

                // ===== Encabezados azules =====
                foreach ([$header1, $header2] as $hr) {
                    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                        'borders'   => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $white]],
                            'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                        ],
                    ]);
                    $ws->getRowDimension($hr)->setRowHeight(17);
                }

                // ===== Título "CONDUCTORES LIBRES" =====
                $ws->mergeCells("A{$title2}:{$lastCol}{$title2}");
                $ws->setCellValue("A{$title2}", 'CONDUCTORES LIBRES');
                $ws->getStyle("A{$title2}:{$lastCol}{$title2}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension($title2)->setRowHeight(16);

                // ===== Bordes datos: punteado horizontal, sólido vertical =====
                $applyDataBorders = function (int $r1, int $r2) use ($ws, $lastCol, $gray, $black) {
                    if ($r2 < $r1) return;
                    $ws->getStyle("A{$r1}:{$lastCol}{$r2}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                };
                if ($this->totalActive > 0) $applyDataBorders($dataStart1, $dataEnd1);
                if ($this->totalFree > 0)    $applyDataBorders($dataStart2, $dataEnd2);

                // ===== Alineaciones =====
                foreach ([[$dataStart1, $dataEnd1, $this->totalActive], [$dataStart2, $dataEnd2, $this->totalFree]] as [$r1, $r2, $cnt]) {
                    if ($cnt <= 0) continue;
                    $ws->getStyle("A{$r1}:{$lastCol}{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ===== Item y Cod en negrita =====
                if ($this->totalActive > 0) {
                    $ws->getStyle("A{$dataStart1}:B{$dataEnd1}")->getFont()->setBold(true);
                }
                if ($this->totalFree > 0) {
                    $ws->getStyle("A{$dataStart2}:B{$dataEnd2}")->getFont()->setBold(true);
                }

                // ===== Footer: estilo compartido =====
                $footerStyle = [
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ];

                // ===== TOTAL ACTIVOS =====
                $ws->mergeCells("A{$foot1}:H{$foot1}");
                $ws->setCellValue("A{$foot1}", 'TOTAL ACTIVOS');
                $ws->setCellValue("I{$foot1}", $this->totalActive);
                $ws->getStyle("A{$foot1}:{$lastCol}{$foot1}")->applyFromArray($footerStyle);
                $ws->getRowDimension($foot1)->setRowHeight(16);

                // ===== TOTAL LIBRES =====
                $ws->mergeCells("A{$foot2}:H{$foot2}");
                $ws->setCellValue("A{$foot2}", 'TOTAL LIBRES');
                $ws->setCellValue("I{$foot2}", $this->totalFree);
                $ws->getStyle("A{$foot2}:{$lastCol}{$foot2}")->applyFromArray($footerStyle);
                $ws->getRowDimension($foot2)->setRowHeight(16);

                $this->applyCompactWidths($ws, 'A', 'I');

                // ===== Ocultar columnas vacías (J en adelante) =====
                foreach (range('J', 'Z') as $col) {
                    $ws->getColumnDimension($col)->setVisible(false);
                }

                // ===== Fuente 10 global =====
                $lastRow = (int) $ws->getHighestRow();
                $ws->getStyle("A1:{$lastCol}{$lastRow}")->getFont()->setSize(10);
            },
        ];
    }


    protected function fetchData(): array
    {
        $statuses = ['active','activo'];
        $filter   = (string) $this->filter;
        $search   = trim((string) $this->search);

        $active = Driver::query()
            ->whereHas('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->withMin(['vehicles as sort_order_min' => function ($q) use ($statuses) {
                $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses);
            }], 'sort_order')
            ->with(['vehicles' => function ($q) use ($statuses) {
                $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
                    ->select('id','driver_id','plate','status','sort_order')
                    ->orderByRaw('sort_order IS NULL, sort_order ASC')
                    ->orderBy('plate');
            }])
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search) ? $q->where('id',(int)$search) : $q,
                    default => $q,
                };
            })
            ->orderByRaw('sort_order_min IS NULL, sort_order_min ASC')
            ->orderBy('name')
            ->get(['id','name','document_number','phone','condition','contract_start','contract_end']);

        $free = Driver::query()
            ->whereDoesntHave('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search) ? $q->where('id',(int)$search) : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->get(['id','name','document_number','phone','condition','contract_start','contract_end']);

        return [$active, $free];
    }
}
