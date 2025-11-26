<?php

namespace App\Exports;

use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class DriversReportExport implements FromArray, WithColumnFormatting, WithEvents, WithColumnWidths
{
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
                optional($d->contract_start)?->format('Y-m-d') ?: null, // I.Contrato
                optional($d->contract_end)?->format('Y-m-d') ?: null,   // F.Contrato
                (string)$d->phone,                              // Teléfono
                (string)$d->condition,                          // Condición
            ];
        }

        // === Tabla 2: Conductores libres ===
        $rows[] = $head;
        foreach ($free as $d) {
            $rows[] = [
                ++$this->rowNumFree,                            // Item
                '—',                                            // Cod
                '—',                                            // Placa
                (string)$d->name,
                (string)$d->document_number,
                optional($d->contract_start)?->format('Y-m-d') ?: null,
                optional($d->contract_end)?->format('Y-m-d') ?: null,
                (string)$d->phone,
                (string)$d->condition,
            ];
        }

        return $rows;
    }


    public function columnFormats(): array
    {
        return [
            'E' => '@',
            'F' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'G' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'H' => '@',
        ];
    }


    public function columnWidths(): array
    {
        return [
            'A' => 4.2,  // Item
            'B' => 5.2,  // Cod
            'E' => 12.0, // N° Documento
            'F' => 9.0,  // I.Contrato
            'G' => 9.0,  // F.Contrato
            'H' => 11.0, // Teléfono
            'I' => 7.0,  // Condición
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                // 🟩 Cambiar nombre de la hoja
                $e->sheet->getDelegate()->setTitle('Conductores');

                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $white    = 'FFFFFFFF';
                $borderC  = 'FFCFD8DC';
                $black    = '000000';
                $red      = 'F80000';

                // Default a 10 (y luego martillazo al final)
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Solo 1 fila para título
                $ws->insertNewRowBefore(1, 1);
                $lastCol = 'I'; // ahora 9 columnas

                // ======= Título =======
                $total = $this->totalActive + $this->totalFree;
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', "REPORTE DE CONDUCTORES (Total: {$total})");
                $ws->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$red]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'=>Alignment::VERTICAL_CENTER
                    ],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(16);

                // ======= Detectar bloques =======
                $header1    = 2;
                $dataStart1 = 3;
                $rowsTotal  = (int)$ws->getHighestRow();
                $header2    = null;

                $firstHeadingLabel = $this->headings()[0] ?? 'Item';

                for ($r = $dataStart1; $r <= $rowsTotal; $r++) {
                    if ((string)$ws->getCell("A{$r}")->getValue() === $firstHeadingLabel) {
                        $header2 = $r;
                        break;
                    }
                }
                if (!$header2) {
                    $header2 = $rowsTotal;
                }

                $total1     = $header2 - 2;
                $dataEnd1   = max($dataStart1, $total1 - 1);
                $dataStart2 = $header2 + 1;
                $total2     = $rowsTotal;
                $dataEnd2   = max($dataStart2, $total2 - 1);

                // ======= Encabezados =======
                foreach ([$header1, $header2] as $hr) {
                    if ($hr < 2) continue;
                    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray([
                        'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'=>Alignment::VERTICAL_CENTER
                        ],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    ]);
                }

                // Zebra
                $zebra = function (string $range) use ($ws) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF3F4F6');
                    $styles = $ws->getStyle($range)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($range)->setConditionalStyles($styles);
                };
                if ($dataEnd1 >= $dataStart1) $zebra("A{$dataStart1}:{$lastCol}{$dataEnd1}");
                if ($dataEnd2 >= $dataStart2) $zebra("A{$dataStart2}:{$lastCol}{$dataEnd2}");

                // ======= Título “CONDUCTORES LIBRES” =======
                $title2 = $header2 - 1; // justo encima del header 2
                $ws->mergeCells("A{$title2}:{$lastCol}{$title2}");
                $ws->setCellValue("A{$title2}", 'CONDUCTORES LIBRES');
                $ws->getStyle("A{$title2}:{$lastCol}{$title2}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$red]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'=>Alignment::VERTICAL_CENTER
                    ],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                ]);
                $ws->getRowDimension($title2)->setRowHeight(16);

                // ======= Alineaciones =======
                foreach ([[$dataStart1,$dataEnd1],[$dataStart2,$dataEnd2]] as [$r1,$r2]) {
                    if ($r2 < $r1) continue;

                    // Item, Cod
                    $ws->getStyle("A{$r1}:B{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Placa
                    $ws->getStyle("C{$r1}:C{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setWrapText(false);

                    // Nombre
                    $ws->getStyle("D{$r1}:D{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(false);

                    // Documento
                    $ws->getStyle("E{$r1}:E{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(false);

                    // Fechas
                    $ws->getStyle("F{$r1}:G{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Teléfono
                    $ws->getStyle("H{$r1}:H{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(false);

                    // Condición
                    $ws->getStyle("I{$r1}:I{$r2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ======= Anchos =======
                $ws->getColumnDimension('A')->setWidth(4.2);   // Item
                $ws->getColumnDimension('B')->setWidth(5.2);   // Cod
                $ws->getColumnDimension('C')->setAutoSize(true); // Placa
                $ws->getColumnDimension('D')->setAutoSize(true); // Nombre
                $ws->getColumnDimension('E')->setWidth(12.0);  // Documento
                $ws->getColumnDimension('F')->setWidth(11.0);  // I.Contrato
                $ws->getColumnDimension('G')->setWidth(11.0);  // F.Contrato
                $ws->getColumnDimension('H')->setWidth(11.0);  // Teléfono
                $ws->getColumnDimension('I')->setWidth(10.0);  // Condición

                // ======= Bordes finos globales + fuente 10 =======
                $lastRow  = (int)$ws->getHighestRow();
                $fullRange = "A1:{$lastCol}{$lastRow}";
                $ws->getStyle($fullRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FF9E9E9E');

                // 🔨 Martillazo final: TODO en tamaño 10
                $ws->getStyle($fullRange)->getFont()->setSize(10);
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
