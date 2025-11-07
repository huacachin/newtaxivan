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

    /** contador para la columna Item */
    private int $rowNum = 0;

    /** Query base para reutilizar en datos y en los totales */
    private function baseQuery(): Builder
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
                    'plate'     => $q->where('plate','like',"%{$search}%"),
                    default     => $q,
                };
            });
    }

    public function query(): Builder
    {
        return $this->baseQuery()
            ->with(['owner:id,name','driver:id,name'])
            // ⬇️ Orden principal por sort_order (no nulos primero), luego id
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('id','asc')
            ->select([
                'id','sort_order','plate','brand','year','class','type','fuel',
                'condition','affiliated_company','owner_id','driver_id'
            ]);
    }

    public function headings(): array
    {
        return [
            'Item',        // A
            'Cod',         // B  (sort_order)
            'Placa',       // C
            'Marca',       // D
            'Año',         // E
            'Categoría',   // F
            'Propietario', // G
            'Conductor',   // H
            'Modalidad',   // I
            'Comb.',       // J
            'Condición',   // K
            'Empresa Afil.'// L
        ];
    }

    public function map($v): array
    {
        $this->rowNum++;

        return [
            $this->rowNum,                            // Item
            $v->sort_order ?? $v->id,                 // Cod (homologado)
            $v->plate,                                // Placa
            $v->brand,                                // Marca
            $v->year,                                 // Año
            $v->class,                                // Categoría
            optional($v->owner)->name ?? '—',         // Propietario
            optional($v->driver)->name ?? '—',        // Conductor
            $v->type,                                 // Modalidad
            $v->fuel,                                 // Comb.
            $v->condition,                            // Condición
            $v->affiliated_company,                   // Empresa Afil.
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4.2,  // Item
            'B' => 5.2,  // Cod
            'C' => 9.2,  // Placa
            'D' => 10.0, // Marca
            'E' => 5.8,  // Año
            'F' => 10.6, // Categoría
            'G' => 22.0, // Propietario
            'H' => 22.0, // Conductor
            'I' => 12.5, // Modalidad
            'J' => 6.0,  // Comb.
            'K' => 7.0,  // Condición
            'L' => 20.0, // Empresa Afil.
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [3 => ['font' => ['bold' => true, 'size' => 10]]];
    }

    /** Totales para el resumen (igual que el Blade) */
    private function stats(): array
    {
        $q = $this->baseQuery();

        $total = (clone $q)->count();
        $d2    = (clone $q)->where('fuel', 'D2')->count();
        $gas   = (clone $q)->where('fuel', 'GAS')->count();

        $vt    = (clone $q)->whereIn('fuel', ['GAS','D2'])->count();
        $vqnt  = (clone $q)->whereNotIn('fuel', ['GAS','D2'])->count();

        // Propietario: V.T y owner_id == driver_id (como tu componente)
        $prop  = (clone $q)->whereIn('fuel', ['GAS','D2'])
            ->whereColumn('owner_id', 'driver_id')->count();

        // Conductor: owner_id != driver_id (sin filtro fuel, como tu componente)
        $cond  = (clone $q)->whereColumn('owner_id', '!=', 'driver_id')->count();

        return compact('total','d2','gas','vt','vqnt','prop','cond');
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
                $black    = '000000';

                // Base 10 pt
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(15);

                // ===== Fila 1: Título (igual al Blade) =====
                $ws->insertNewRowBefore(1, 2);
                $ws->mergeCells('A1:L1');
                $ws->setCellValue('A1', 'VEHÍCULOS');
                $ws->getStyle('A1:L1')->applyFromArray([
                    'font'      => ['bold'=>true,'size'=>10,'color'=>['argb'=>$black]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(18);

                // ===== Fila 2: Resumen (Total, D2, Gas, V.T, V.Q.N.T, Propietario, Conductor) =====
                $s = $this->stats();
                $resume = sprintf(
                    'Total vehículos: %d · D2: %d · Gas: %d · V.T: %d · V.Q.N.T: %d · Propietario: %d · Conductor: %d',
                    $s['total'], $s['d2'], $s['gas'], $s['vt'], $s['vqnt'], $s['prop'], $s['cond']
                );
                $ws->mergeCells('A2:L2');
                $ws->setCellValue('A2', $resume);
                $ws->getStyle('A2:L2')->applyFromArray([
                    'font'      => ['italic'=>true,'size'=>10,'color'=>['argb'=>$black]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                ]);
                $ws->getRowDimension(2)->setRowHeight(16);

                // ===== Encabezado de tabla =====
                $headerRow    = 3;
                $dataStartRow = 4;
                $lastCol      = 'L';

                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(17);

                // Congelar encabezado
                //$ws->freezePane("A{$dataStartRow}");

                // Bordes
                $last = (int)$ws->getHighestRow();
                $ws->getStyle("A{$headerRow}:{$lastCol}".max($last,$headerRow))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($borderC);

                // Datos 10 pt
                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$last}")->getFont()->setSize(10);
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

                // Alineaciones + ajuste
                $ws->getStyle("A{$dataStartRow}:B{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("C{$dataStartRow}:F{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("G{$dataStartRow}:H{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true)->setWrapText(false);
                $ws->getStyle("I{$dataStartRow}:K{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("L{$dataStartRow}:L{$last}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true)->setWrapText(false);

                // Pie
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:K{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL VEHÍCULOS');
                $ws->setCellValue("L{$totalRow}", "=COUNT(A{$dataStartRow}:A{$last})");
                $ws->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                    'fill'    => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    'font'    => ['bold'=>true,'size'=>10,'color'=>['argb'=>'FF000000']],
                    'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                ]);
                $ws->getStyle("A{$totalRow}:K{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("L{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
