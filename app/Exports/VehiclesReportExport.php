<?php

namespace App\Exports;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class VehiclesReportExport implements
    FromQuery,
    ShouldAutoSize,
    WithHeadings,
    WithMapping,
    WithColumnFormatting,
    WithStyles,
    WithEvents
{
    public function __construct(
        protected ?string $status = 'active',
        protected ?string $search = null,
        protected ?string $filter = 'plate'
    ) {}

    /* ========================= QUERY ========================= */
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
                    'plate'     => $q->where('plate', 'like', "%{$search}%"),
                    'brand'     => $q->where('brand', 'like', "%{$search}%"),
                    'category'  => $q->where('class', 'like', "%{$search}%"),
                    'year'      => ctype_digit($search) ? $q->where('year', (int)$search) : $q->where('year','like',"%{$search}%"),
                    'owner'     => $q->whereHas('owner',  fn($r) => $r->where('name','like',"%{$search}%")),
                    'driver'    => $q->whereHas('driver', fn($r) => $r->where('name','like',"%{$search}%")),
                    'condition' => $q->where('condition','like',"%{$search}%"),
                    'company'   => $q->where('affiliated_company','like',"%{$search}%"),
                    'code'      => ctype_digit($search) ? $q->where('id',(int)$search) : $q,
                    default     => $q,
                };
            })
            ->with(['owner:id,name','driver:id,name'])
            ->orderBy('plate')
            ->select([
                'id','owner_id','driver_id','plate','status','year','condition',
                'affiliated_company','termination_date','brand','class','type',
                'fuel','headquarters','entry_date','soat_date','technical_review',
                'certificate_date','model','bodywork','color',
            ]);
    }

    /* ========================= LAYOUT / MAPPING ========================= */
    public function headings(): array
    {
        return [
            'ID','Placa','Marca','Modelo','Año','Carrocería','Color','Categoría','Tipo',
            'Combustible','Condición','Compañía Afiliada','Sede','Estado','Propietario',
            'Conductor','Ingreso','Término','SOAT','Rev. Técnica','Certificado',
        ];
    }

    public function map($v): array
    {
        $d = fn($x) => $x ? Carbon::parse($x) : null;

        return [
            $v->id,
            $v->plate,
            $v->brand,
            $v->model,
            $v->year,
            $v->bodywork,
            $v->color,
            $v->class,
            $v->type,
            $v->fuel,
            $v->condition,
            $v->affiliated_company,
            $v->headquarters,
            strtoupper((string)$v->status),
            optional($v->owner)->name ?? '—',
            optional($v->driver)->name ?? '—',
            $d($v->entry_date),
            $d($v->termination_date),
            $d($v->soat_date),
            $d($v->technical_review),
            $d($v->certificate_date),
        ];
    }

    public function columnFormats(): array
    {
        // A..U  (Q..U son fechas)
        return [
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'R' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'S' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'T' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'U' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header real queda en fila 3; negrita para asegurar
        return [1 => ['font' => ['bold' => true]]];
    }

    /* ========================= ESTILOS (copiados del Payments) ========================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertar 2 filas: Título(1) + Subtítulo(2)
                $ws->insertNewRowBefore(1, 2);

                // ===== Título oscuro =====
                $ws->setCellValue('A1', 'REPORTE DE VEHÍCULOS');
                $ws->mergeCells('A1:U1');
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(1)->setRowHeight(24);

                // ===== Subtítulo (filtros) =====
                $sub = sprintf(
                    'Generado: %s | Estado: %s%s%s',
                    now()->format('Y-m-d H:i'),
                    $this->status ?? '—',
                    $this->filter ? " | Filtro: {$this->filter}" : '',
                    ($this->search ?? '') !== '' ? " = '{$this->search}'" : ''
                );
                $ws->setCellValue('A2', $sub);
                $ws->mergeCells('A2:U2');
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $ws->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(2)->setRowHeight(18);

                // ===== Thead (fila 3) oscuro como tu Payments =====
                $headerRow    = 3;
                $dataStartRow = 4;
                $lastCol      = 'U';
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                $ws->getRowDimension($headerRow)->setRowHeight(20);

                // Congelar encabezado
                $ws->freezePane("A{$dataStartRow}");

                // Última fila con datos
                $last = (int)$ws->getHighestRow();
                if ($last < $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
                    return;
                }

                // Autofiltro + bordes finos
                $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$last}");
                $ws->getStyle("A{$headerRow}:{$lastCol}{$last}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCFD8DC');

                // Zebra stripes (gris muy suave)
                $rangeData = "A{$dataStartRow}:{$lastCol}{$last}";
                $cond = new Conditional();
                $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                $cond->setConditions(['MOD(ROW(),2)=0']);
                $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF9FAFB');
                $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($rangeData)->setConditionalStyles($styles);

                // Anchos súper compactos
                $ws->getColumnDimension('A')->setWidth(6);   // ID
                $ws->getColumnDimension('B')->setWidth(11);  // Placa
                $ws->getColumnDimension('C')->setWidth(12);  // Marca
                $ws->getColumnDimension('D')->setWidth(13);  // Modelo
                $ws->getColumnDimension('E')->setWidth(6);   // Año
                $ws->getColumnDimension('F')->setWidth(11);  // Carrocería
                $ws->getColumnDimension('G')->setWidth(9);   // Color
                $ws->getColumnDimension('H')->setWidth(11);  // Categoría
                $ws->getColumnDimension('I')->setWidth(10);  // Tipo
                $ws->getColumnDimension('J')->setWidth(10);  // Combustible
                $ws->getColumnDimension('K')->setWidth(10);  // Condición
                $ws->getColumnDimension('L')->setWidth(14);  // Compañía
                $ws->getColumnDimension('M')->setWidth(11);  // Sede
                $ws->getColumnDimension('N')->setWidth(9);   // Estado
                $ws->getColumnDimension('O')->setWidth(16);  // Propietario
                $ws->getColumnDimension('P')->setWidth(16);  // Conductor
                $ws->getColumnDimension('Q')->setWidth(10);  // Ingreso
                $ws->getColumnDimension('R')->setWidth(10);  // Término
                $ws->getColumnDimension('S')->setWidth(10);  // SOAT
                $ws->getColumnDimension('T')->setWidth(11);  // Rev. Téc.
                $ws->getColumnDimension('U')->setWidth(11);  // Certificado

                // Alineaciones compactas
                $ws->getStyle("A{$dataStartRow}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("B{$dataStartRow}:D{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("E{$dataStartRow}:E{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("F{$dataStartRow}:M{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("N{$dataStartRow}:N{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("O{$dataStartRow}:P{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle("Q{$dataStartRow}:U{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ===== Pie (tfoot) oscuro igual al thead =====
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:T{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL VEHÍCULOS');
                $ws->setCellValue("U{$totalRow}", "=COUNT(A{$dataStartRow}:A{$last})");

                $ws->getStyle("A{$totalRow}:U{$totalRow}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$totalRow}:U{$totalRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                $ws->getStyle("A{$totalRow}:U{$totalRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $ws->getStyle("A{$totalRow}:T{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("U{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
