<?php

namespace App\Exports;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

class DriversReportExport implements
    FromQuery, WithHeadings, WithMapping, ShouldAutoSize,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected ?string $search = null,
        protected ?string $filter = 'plate'
    ) {}

    public function query(): Builder
    {
        $filter = (string) $this->filter;
        $search = trim((string) $this->search);

        return Driver::query()
            // Solo drivers con vehículos ACTIVOS
            ->whereHas('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), ['active','activo'])
            )
            ->with(['vehicles' => fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), ['active','activo'])
                ->select('id','driver_id','plate','status')
            ])
            // Filtros
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->whereHas('vehicles', fn($qq) => $qq->where('id', $search))
                        : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->select([
                'id','name','document_number','phone','email','address','district',
                'license','class','category',
                'license_issue_date','license_revalidation_date',
                'contract_start','contract_end',
                'condition','score',
                'document_expiration_date','birthdate',
                'credential','credential_expiration_date','credential_municipality',
            ]);
    }

    public function headings(): array
    {
        return [
            'ID','Nombre','N° Documento','Teléfono','Email','Dirección','Distrito',
            'Licencia','Clase','Categoría',
            'F. Emisión Licencia','F. Revalidación Licencia',
            'Contrato Inicio','Contrato Fin',
            'Condición','Score',
            'Venc. Documento','Nacimiento',
            'Credencial','Venc. Credencial','Municipalidad Credencial',
            'Placas Activas',
        ];
    }

    public function map($d): array
    {
        $plates = $d->relationLoaded('vehicles')
            ? $d->vehicles->pluck('plate')->filter()->values()->implode(', ')
            : '';

        return [
            $d->id,
            $d->name,
            $d->document_number,
            $d->phone,
            $d->email,
            $d->address,
            $d->district,
            $d->license,
            $d->class,
            $d->category,
            optional($d->license_issue_date)?->format('Y-m-d') ?: null,
            optional($d->license_revalidation_date)?->format('Y-m-d') ?: null,
            optional($d->contract_start)?->format('Y-m-d') ?: null,
            optional($d->contract_end)?->format('Y-m-d') ?: null,
            $d->condition,
            is_null($d->score) ? null : (float) $d->score,
            optional($d->document_expiration_date)?->format('Y-m-d') ?: null,
            optional($d->birthdate)?->format('Y-m-d') ?: null,
            $d->credential, // si fuera fecha en tu modelo, cámbialo a optional(...)->format()
            optional($d->credential_expiration_date)?->format('Y-m-d') ?: null,
            $d->credential_municipality,
            $plates,
        ];
    }

    public function columnFormats(): array
    {
        // A..V (ver headings)
        return [
            'K' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Emisión Licencia
            'L' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Revalidación
            'M' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Contrato Inicio
            'N' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Contrato Fin
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Venc. Documento
            'R' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Nacimiento
            'T' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Venc. Credencial
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
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
