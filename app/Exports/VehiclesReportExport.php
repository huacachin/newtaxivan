<?php

namespace App\Exports;

use App\Models\Vehicle;
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

class VehiclesReportExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithColumnFormatting,
    WithStyles,
    WithEvents
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
            ->when(in_array($status, ['active', 'inactive'], true),
                fn ($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', [$status])
            )
            ->when($search !== '' && $filter !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->where('plate', 'like', "%{$search}%"),
                    'brand' => $q->where('brand', 'like', "%{$search}%"),
                    'category' => $q->where('class', 'like', "%{$search}%"),
                    'year' => ctype_digit($search)
                        ? $q->where('year', (int) $search)
                        : $q->where('year', 'like', "%{$search}%"),
                    'owner' => $q->whereHas('owner', fn($r) => $r->where('name', 'like', "%{$search}%")),
                    'driver' => $q->whereHas('driver', fn($r) => $r->where('name', 'like', "%{$search}%")),
                    'condition' => $q->where('condition', 'like', "%{$search}%"),
                    'company' => $q->where('affiliated_company', 'like', "%{$search}%"),
                    'code' => ctype_digit($search) ? $q->where('id', (int)$search) : $q,
                    default => $q,
                };
            })
            ->with(['owner:id,name', 'driver:id,name'])
            ->where('status', 'active')
            ->select([
                'id', 'owner_id', 'driver_id', 'plate', 'status', 'year', 'condition',
                'affiliated_company', 'termination_date', 'brand', 'class', 'type',
                'fuel', 'headquarters', 'entry_date', 'soat_date', 'technical_review',
                'certificate_date', 'model', 'bodywork', 'color',
            ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Placa',
            'Marca',
            'Modelo',
            'Año',
            'Carrocería',
            'Color',
            'Categoría',
            'Tipo',
            'Combustible',
            'Condición',
            'Compañía Afiliada',
            'Sede',
            'Estado',
            'Propietario',
            'Conductor',
            'Ingreso',
            'Termino',
            'SOAT',
            'Rev. Técnica',
            'Certificado',
        ];
    }

    public function map($v): array
    {
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
            optional($v->entry_date)?->format('Y-m-d') ?: null,
            optional($v->termination_date)?->format('Y-m-d') ?: null,
            optional($v->soat_date)?->format('Y-m-d') ?: null,
            optional($v->technical_review)?->format('Y-m-d') ?: null,
            optional($v->certificate_date)?->format('Y-m-d') ?: null,
        ];
    }

    public function columnFormats(): array
    {
        // Columnas de fecha (Q->?) según orden de headings
        // ID(A) Placa(B) Marca(C) Modelo(D) Año(E) Carrocería(F) Color(G)
        // Categoría(H) Tipo(I) Combustible(J) Condición(K) Compañía(L) Sede(M)
        // Estado(N) Prop(O) Cond(P) Ingreso(Q) Término(R) SOAT(S) Rev.Téc.(T) Cert.(U)
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
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();
                $ws->freezePane('A2');
                $ws->setAutoFilter($ws->calculateWorksheetDimension());
            },
        ];
    }
}
