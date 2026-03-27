<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeparturesMainExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents, WithTitle
{
    public function __construct(
        protected ?int $searchType = 1,
        protected ?string $searchText = null,
        protected ?string $fromDate = null,
        protected ?string $toDate = null,
        protected bool $groupMode = false
    ) {}

    public function title(): string { return 'Principal'; }

    private function base(): Builder
    {
        $q = DB::table('departures as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->whereIn(DB::raw("LOWER(TRIM(v.status))"), ['active','activo']);

        // Rango de fechas
        if ($this->fromDate && $this->toDate)       $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        elseif ($this->fromDate)                     $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                       $q->whereDate('d.date', '<=', $this->toDate);

        // Búsqueda
        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ((int)$this->searchType) {
                case 1: $q->where('v.plate', 'like', '%'.strtoupper($term).'%'); break;
                case 2: $q->where('u.name', 'like', '%'.$term.'%'); break;
                case 3:
                    if (is_numeric($term)) $q->where('h.id', (int)$term);
                    else $q->where('h.name', 'like', '%'.$term.'%');
                    break;
            }
        }
        return $q;
    }

    public function query(): Builder
    {
        if ($this->groupMode) {
            // Agrupado por placa
            return $this->base()
                ->selectRaw("
                    v.plate as plate,
                    ANY_VALUE(h.name) as headquarter_name,
                    ANY_VALUE(u.name) as user_name,
                    COALESCE(SUM(d.times),0)  as k1,
                    COALESCE(SUM(d.price),0)  as p1,
                    COALESCE(SUM(d.passenger),0) as pasajeros,
                    COALESCE(SUM(d.passage),0)   as pasaje,
                    COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)),0) as total_pasaje,
                    MIN(d.date) as from_date,
                    MAX(d.date) as to_date
                ")
                ->groupBy('v.plate')
                ->orderBy('v.plate');
        }

        // Detalle + frecuencia
        $inner = $this->base()
            ->selectRaw("
                d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                d.latitude, d.longitude,
                v.plate as plate,
                h.name as headquarter_name,
                u.name as user_name,
                COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                CONCAT(d.date,' ',d.hour) as curr_dt,
                LAG(CONCAT(d.date,' ',d.hour)) OVER (PARTITION BY v.plate ORDER BY d.date, d.hour) as prev_dt
            ");

        return DB::query()
            ->fromSub($inner, 'x')
            ->selectRaw("x.*, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, x.prev_dt, x.curr_dt)) as freq")
            ->orderBy('x.date')->orderBy('x.hour');
    }

    public function headings(): array
    {
        if ($this->groupMode) {
            return [
                'Placa','Sucursal','Usuario',
                'K1 (veces)','P1 (precio)','Pasajeros','Pasaje','Total Pasaje',
                'Desde','Hasta'
            ];
        }
        return [
            'Placa','Sucursal','Usuario',
            'Fecha','Hora','Frecuencia',
            'Veces','Precio','Pasajeros','Pasaje','Total Pasaje',
            'Latitud','Longitud'
        ];
    }

    public function map($row): array
    {
        if ($this->groupMode) {
            return [
                $row->plate,
                $row->headquarter_name,
                $row->user_name,
                (int)($row->k1 ?? 0),
                (float)($row->p1 ?? 0),
                (int)($row->pasajeros ?? 0),
                (float)($row->pasaje ?? 0),
                (float)($row->total_pasaje ?? 0),
                $row->from_date,
                $row->to_date,
            ];
        }
        return [
            $row->plate,
            $row->headquarter_name,
            $row->user_name,
            $row->date,
            $row->hour,
            $row->freq, // HH:MM:SS
            (int)($row->times ?? 0),
            (float)($row->price ?? 0),
            (int)($row->passenger ?? 0),
            (float)($row->passage ?? 0),
            (float)($row->total_pasaje ?? 0),
            $row->latitude,
            $row->longitude,
        ];
    }

    public function columnFormats(): array
    {
        if ($this->groupMode) {
            return [
                'D' => NumberFormat::FORMAT_NUMBER,      // K1
                'E' => NumberFormat::FORMAT_NUMBER_00,   // P1
                'F' => NumberFormat::FORMAT_NUMBER,      // Pasajeros
                'G' => NumberFormat::FORMAT_NUMBER_00,   // Pasaje
                'H' => NumberFormat::FORMAT_NUMBER_00,   // Total
                'I' => 'dd/mm/yyyy', // Desde
                'J' => 'dd/mm/yyyy', // Hasta
            ];
        }
        return [
            'D' => 'dd/mm/yyyy', // Fecha
            'E' => NumberFormat::FORMAT_DATE_TIME3,     // Hora
            'G' => NumberFormat::FORMAT_NUMBER,         // Veces
            'H' => NumberFormat::FORMAT_NUMBER_00,      // Precio
            'I' => NumberFormat::FORMAT_NUMBER,         // Pasajeros
            'J' => NumberFormat::FORMAT_NUMBER_00,      // Pasaje
            'K' => NumberFormat::FORMAT_NUMBER_00,      // Total
        ];
    }

    public function styles(Worksheet $sheet) { return [1 => ['font' => ['bold' => true]]]; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();
                $ws->freezePane('A2');
                $ws->setAutoFilter($ws->calculateWorksheetDimension());
            },
        ];
    }
}
