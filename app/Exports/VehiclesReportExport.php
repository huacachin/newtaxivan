<?php

namespace App\Exports;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class VehiclesReportExport implements
    FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, WithColumnWidths
{
    public function __construct(
        protected ?string $status = 'active',
        protected ?string $search = null,
        protected ?string $filter = 'plate'
    ) {}

    public function query(): Builder
    {
        $status = strtolower(trim((string) $this->status));
        $search = trim((string) $this->search);
        $filter = (string) $this->filter;

        return Vehicle::query()
            ->when(in_array($status, ['active','inactive'], true),
                fn ($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', [$status])
            )
            ->when($search !== '' && $filter !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'brand'     => $q->where('brand', 'like', "%{$search}%"),
                    'owner'     => $q->whereHas('owner',  fn($r) => $r->where('name','like',"%{$search}%")),
                    'driver'    => $q->whereHas('driver', fn($r) => $r->where('name','like',"%{$search}%")),
                    'type'      => $q->where('type','like',"%{$search}%"),
                    'fuel'      => $q->where('fuel','like',"%{$search}%"),
                    'condition' => $q->where('condition','like',"%{$search}%"),
                    'company'   => $q->where('affiliated_company','like',"%{$search}%"),
                    default     => $q,
                };
            })
            ->with(['owner:id,name','driver:id,name'])
            ->orderBy('brand')
            ->select(['id','owner_id','driver_id','brand','type','fuel','condition','affiliated_company']);
    }

    public function headings(): array
    {
        return ['ID','Marca','Propietario','Conductor','Tipo','Combustible','Condición','Compañía Afiliada'];
    }

    public function map($v): array
    {
        return [
            $v->id,
            $v->brand,
            optional($v->owner)->name ?? '—',
            optional($v->driver)->name ?? '—',
            $v->type,
            $v->fuel,
            $v->condition,
            $v->affiliated_company,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4.6,  // ID
            'B' => 8.8,  // Marca
            'C' => 16.5, // Propietario
            'D' => 16.5, // Conductor
            'E' => 7.8,  // Tipo
            'F' => 7.2,  // Combustible
            'G' => 6.6,  // Condición
            'H' => 17.6, // Compañía Afiliada
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $borderC  = 'FFCFD8DC';

                // Título (12pt) y subtítulo (10pt)
                $ws->insertNewRowBefore(1, 2);
                $ws->mergeCells('A1:H1');
                $ws->setCellValue('A1', 'REPORTE DE VEHÍCULOS');
                $ws->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>12,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                $sub = sprintf(
                    'Generado: %s | Estado: %s%s%s',
                    now()->format('Y-m-d H:i'),
                    $this->status ?? '—',
                    $this->filter ? " | Filtro: {$this->filter}" : '',
                    ($this->search ?? '') !== '' ? " = '{$this->search}'" : ''
                );
                $ws->mergeCells('A2:H2');
                $ws->setCellValue('A2', $sub);
                $ws->getStyle('A2:H2')->applyFromArray([
                    'font' => ['italic'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension(2)->setRowHeight(16);

                // Encabezado (11pt)
                $headerRow    = 3;
                $dataStartRow = 4;
                $lastCol      = 'H';

                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>11,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // Congelar
                $ws->freezePane("A{$dataStartRow}");

                // Bordes
                $last = (int)$ws->getHighestRow();
                $ws->getStyle("A{$headerRow}:{$lastCol}".max($last,$headerRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($borderC);

                // Datos (11pt)
                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$last}")->getFont()->setSize(11);
                }

                // Zebra
                if ($last >= $dataStartRow) {
                    $rangeData = "A{$dataStartRow}:{$lastCol}{$last}";
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF3F4F6');
                    $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($rangeData)->setConditionalStyles($styles);
                }

                // Refuerzo de anchos
                $ws->getColumnDimension('A')->setWidth(4.6);
                $ws->getColumnDimension('B')->setWidth(8.8);
                $ws->getColumnDimension('C')->setWidth(16.5);
                $ws->getColumnDimension('D')->setWidth(16.5);
                $ws->getColumnDimension('E')->setWidth(7.8);
                $ws->getColumnDimension('F')->setWidth(7.2);
                $ws->getColumnDimension('G')->setWidth(6.6);
                $ws->getColumnDimension('H')->setWidth(17.6);

                // Alineaciones + shrink en columnas largas
                $ws->getStyle("A{$dataStartRow}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("B{$dataStartRow}:B{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("C{$dataStartRow}:D{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true)->setWrapText(false);
                $ws->getStyle("E{$dataStartRow}:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("H{$dataStartRow}:H{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true)->setWrapText(false);

                // Pie
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:G{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL VEHÍCULOS');
                $ws->setCellValue("H{$totalRow}", "=COUNT(A{$dataStartRow}:A{$last})");
                $ws->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    'font' => ['bold'=>true,'size'=>11,'color'=>['argb'=>'FF000000']],
                    'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                ]);
                $ws->getStyle("A{$totalRow}:G{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
