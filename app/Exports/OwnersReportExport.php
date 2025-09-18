<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
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

class OwnersReportExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithColumnFormatting,
    WithStyles,
    WithEvents
{
    public function __construct(
        protected ?string $search = null,
        protected string $filter = 'plate'
    ) {}

    public function query(): Builder
    {
        $search = trim((string)$this->search);

        // Escapar % y _ para LIKE, como en tu componente
        $like = $search === ''
            ? null
            : '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

        return DB::table('owners as o')
            ->leftJoin('vehicles as v', function ($join) {
                $join->on('v.owner_id', '=', 'o.id')
                    ->whereIn(DB::raw('LOWER(TRIM(v.status))'), ['active','activo']);
            })
            ->when($like !== null, function ($q) use ($like) {
                $q->when($this->filter === 'name', fn($qq) => $qq->where('o.name', 'like', $like))
                    ->when($this->filter === 'code', fn($qq) => $qq->where('o.id', 'like', $like))
                    ->when($this->filter === 'plate', fn($qq) => $qq->where('v.plate', 'like', $like));
            })
            // Si filtras por plate y NO hay término de búsqueda,
            // lista solo owners con alguna placa activa (igual a tu componente)
            ->when($this->filter === 'plate' && $like === null, fn($q) => $q->whereNotNull('v.id'))
            ->select([
                'o.id',
                'o.name',
                'o.document_type',
                'o.document_number',
                'o.document_expiration_date',
                'o.birthdate',
                'o.address',
                'o.district',
                'o.email',
                'o.phone',
                'v.plate', // puede venir NULL
            ])
            ->orderBy('o.name')
            ->orderByRaw('v.plate IS NULL, v.plate');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Tipo Doc.',
            'N° Documento',
            'Venc. Documento',
            'Nacimiento',
            'Dirección',
            'Distrito',
            'Email',
            'Teléfono',
            'Placa (activa)',
        ];
    }

    public function map($row): array
    {
        // $row es stdClass porque usamos Query Builder
        return [
            $row->id,
            $row->name,
            $row->document_type,
            $row->document_number,
            !empty($row->document_expiration_date) ? Carbon::parse($row->document_expiration_date) : null,
            !empty($row->birthdate) ? Carbon::parse($row->birthdate) : null,
            $row->address,
            $row->district,
            $row->email,
            $row->phone,
            $row->plate ?? '—',
        ];
    }

    public function columnFormats(): array
    {
        // A B C D E F G H I J K
        return [
            'E' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
            'F' => NumberFormat::FORMAT_DATE_YYYYMMDD2,
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
