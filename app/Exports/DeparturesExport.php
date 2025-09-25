<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DeparturesExport implements FromView, ShouldAutoSize, WithEvents
{
    public function __construct(
        public readonly int     $searchType = 1,
        public readonly ?string $searchText = null,
        public readonly ?string $fromDate   = null,
        public readonly ?string $toDate     = null,
        public readonly bool    $groupMode  = false,
    ) {}

    private int $countExisting = 0;
    private int $countSupport  = 0;

    public function view(): View
    {
        [$rows, $totals]           = $this->getExisting();
        [$supportRows, $supTotals] = $this->getSupport();

        $this->countExisting = $rows->count();
        $this->countSupport  = $supportRows->count();

        $grand = (object)[
            'times_total'        => (int)   (($totals->times_total ?? 0) + ($supTotals->times_total ?? 0)),
            'price_total'        => (float) (($totals->price_total ?? 0) + ($supTotals->price_total ?? 0)),
            'passengers_total'   => (int)   (($totals->passengers_total ?? 0) + ($supTotals->passengers_total ?? 0)),
            'passage_total'      => (float) (($totals->passage_total ?? 0) + ($supTotals->passage_total ?? 0)),
            'total_pasaje_total' => (float) (($totals->total_pasaje_total ?? 0) + ($supTotals->total_pasaje_total ?? 0)),
        ];

        $filters = [
            'searchType' => $this->searchType,
            'searchText' => $this->searchText,
            'fromDate'   => $this->fromDate,
            'toDate'     => $this->toDate,
            'groupMode'  => $this->groupMode,
        ];

        return view('exports.departures', compact('rows','totals','supportRows','supTotals','grand','filters'));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();

                // Paleta
                $bgTitle   = 'FF1F4E79'; // azul oscuro
                $bgHeader  = 'FFD9E1F2'; // celeste claro
                $bgTotal   = 'FFE2EFDA'; // verde claro
                $fontWhite = 'FFFFFFFF';

                // Filas (coinciden EXACTO con la vista)
                $rTitle = 1; // A1..N1
                $rSub   = 2; // A2..N2
                $rBlank = 3;

                // Sección 1
                $rSec1Title  = 4; // A4..N4
                $rSec1Hdr1   = 5; // A5..N5
                $rSec1Hdr2   = 6; // A6..N6
                $rSec1Body1  = 7; // inicio cuerpo
                $rSec1BodyN  = $rSec1Body1 + max(1, $this->countExisting) - 1;
                $rSec1Total  = $rSec1BodyN + 1;
                $rBlank2     = $rSec1Total + 1;

                // Sección 2
                $rSec2Title  = $rBlank2 + 1;
                $rSec2Hdr1   = $rSec2Title + 1;
                $rSec2Hdr2   = $rSec2Title + 2;
                $rSec2Body1  = $rSec2Hdr2 + 1;
                $rSec2BodyN  = $rSec2Body1 + max(1, $this->countSupport) - 1;
                $rSec2Total  = $rSec2BodyN + 1;
                $rBlank3     = $rSec2Total + 1;

                // Total general
                $rGrand      = $rBlank3 + 1;

                // Última fila usada
                $lastRow     = $rGrand;

                // Merges de título y subtítulo
                foreach ([$rTitle,$rSub,$rSec1Title,$rSec2Title] as $r) {
                    $s->mergeCells("A{$r}:N{$r}");
                }

                // Encabezados (2 filas) — secciones 1 y 2
                $this->mergeHeader($s, $rSec1Hdr1, $rSec1Hdr2);
                $this->mergeHeader($s, $rSec2Hdr1, $rSec2Hdr2);

                // Estilos: título
                $s->getStyle("A{$rTitle}:N{$rTitle}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bgTitle]],
                    'font' => ['bold'=>true,'size'=>14,'color'=>['argb'=>$fontWhite]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                // Subtítulo
                $s->getStyle("A{$rSub}:N{$rSub}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Headers
                foreach ([[$rSec1Hdr1,$rSec1Hdr2],[$rSec2Hdr1,$rSec2Hdr2]] as [$h1,$h2]) {
                    $s->getStyle("A{$h1}:N{$h2}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bgHeader]],
                        'font' => ['bold'=>true],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                    ]);
                    $s->getRowDimension($h1)->setRowHeight(22);
                    $s->getRowDimension($h2)->setRowHeight(20);
                }

                // Totales y total general (banda)
                foreach ([$rSec1Total, $rSec2Total, $rGrand] as $rt) {
                    $s->getStyle("A{$rt}:N{$rt}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$bgTotal]],
                        'font' => ['bold'=>true],
                    ]);
                }

                // Bordes finos a todo el rango
                $s->getStyle("A1:N{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFBFBFBF']
                        ]
                    ]
                ]);

                // Alineación derecha para números (H..M)
                foreach (['H','I','J','K','L','M'] as $col) {
                    $s->getStyle("{$col}{$rSec1Body1}:{$col}{$rSec1Total}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$col}{$rSec2Body1}:{$col}{$rSec2Total}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$col}{$rGrand}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Congelar arriba de la primera cabecera
                $s->freezePane("A7");

                // Opcional: Autofiltro en la cabecera de la sección 1
                // $s->setAutoFilter("A{$rSec1Hdr2}:N{$rSec1Hdr2}");
            },
        ];
    }

    private function mergeHeader($sheet, int $r1, int $r2): void
    {
        // Vertical (A,B,C,F,G,N ocupan 2 filas)
        foreach (['A','B','C','F','G','N'] as $col) {
            $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
        }
        // Grupos horizontales
        $sheet->mergeCells("D{$r1}:E{$r1}"); // Hora
        $sheet->mergeCells("H{$r1}:J{$r1}"); // Empresa
        $sheet->mergeCells("K{$r1}:M{$r1}"); // Vehículo
    }

    // =====================  QUERIES  =====================

    private function baseExisting()
    {
        $q = DB::table('departures as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->where('v.status', 'active');

        if ($this->fromDate && $this->toDate)       $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        elseif ($this->fromDate)                     $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                       $q->whereDate('d.date', '<=', $this->toDate);

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

    private function baseSupport()
    {
        $q = DB::table('departures as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->where('d.is_support', 1);

        if ($this->fromDate && $this->toDate)       $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        elseif ($this->fromDate)                     $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                       $q->whereDate('d.date', '<=', $this->toDate);

        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ((int)$this->searchType) {
                case 1: $q->where('d.legacy_plate', 'like', '%'.strtoupper($term).'%'); break;
                case 2: $q->where('u.name', 'like', '%'.$term.'%'); break;
                case 3:
                    if (is_numeric($term)) $q->where('h.id', (int)$term);
                    else $q->where('h.name', 'like', '%'.$term.'%');
                    break;
            }
        }
        return $q;
    }

    private function getExisting()
    {
        if ($this->groupMode) {
            $agg = $this->baseExisting()
                ->selectRaw("
                    v.plate as plate,
                    ANY_VALUE(h.name) as headquarter_name,
                    ANY_VALUE(u.name) as user_name,
                    COALESCE(SUM(d.times), 0)  as times,
                    COALESCE(SUM(d.price), 0)  as price,
                    COALESCE(SUM(d.passenger), 0) as passenger,
                    COALESCE(SUM(d.passage), 0)   as passage,
                    COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)),0) as total_pasaje
                ")
                ->groupBy('v.plate')
                ->orderBy('v.plate')
                ->get();

            $tot = $this->totalsFor($this->baseExisting());
            return [$agg, $tot];
        }

        // Detalle con frecuencia
        $inner = $this->baseExisting()
            ->selectRaw("
                d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                d.latitude, d.longitude,
                v.plate as plate,
                h.name as headquarter_name, u.name as user_name,
                COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                CONCAT(d.date,' ',d.hour) as curr_dt,
                LAG(CONCAT(d.date,' ',d.hour)) OVER (PARTITION BY v.plate ORDER BY d.date, d.hour) as prev_dt
            ");

        $rows = DB::query()
            ->fromSub($inner, 'x')
            ->selectRaw("x.*, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, x.prev_dt, x.curr_dt)) as freq")
            ->orderBy('x.date')->orderBy('x.hour')
            ->get();

        $tot = $this->totalsFor($this->baseExisting());
        return [$rows, $tot];
    }

    private function getSupport()
    {
        if ($this->groupMode) {
            $agg = $this->baseSupport()
                ->selectRaw("
                    d.legacy_plate as plate,
                    ANY_VALUE(h.name) as headquarter_name,
                    ANY_VALUE(u.name) as user_name,
                    COALESCE(SUM(d.times), 0)  as times,
                    COALESCE(SUM(d.price), 0)  as price,
                    COALESCE(SUM(d.passenger), 0) as passenger,
                    COALESCE(SUM(d.passage), 0)   as passage,
                    COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)),0) as total_pasaje
                ")
                ->groupBy('d.legacy_plate')
                ->orderBy('d.legacy_plate')
                ->get();

            $tot = $this->totalsFor($this->baseSupport());
            return [$agg, $tot];
        }

        $inner = $this->baseSupport()
            ->selectRaw("
                d.id, d.date, d.hour, d.times, d.price, d.passenger, d.passage,
                d.latitude, d.longitude,
                d.legacy_plate as plate,
                h.name as headquarter_name, u.name as user_name,
                COALESCE(d.passenger,0)*COALESCE(d.passage,0) as total_pasaje,
                CONCAT(d.date,' ',d.hour) as curr_dt,
                LAG(CONCAT(d.date,' ',d.hour)) OVER (PARTITION BY d.legacy_plate ORDER BY d.date, d.hour) as prev_dt
            ");

        $rows = DB::query()
            ->fromSub($inner, 'x')
            ->selectRaw("x.*, SEC_TO_TIME(TIMESTAMPDIFF(SECOND, x.prev_dt, x.curr_dt)) as freq")
            ->orderBy('x.date')->orderBy('x.hour')
            ->get();

        $tot = $this->totalsFor($this->baseSupport());
        return [$rows, $tot];
    }

    private function totalsFor($base): object
    {
        $row = $base->cloneWithout(['orders','columns'])
            ->selectRaw('
                COUNT(*) as records,
                COALESCE(SUM(d.times),0) as times_total,
                COALESCE(SUM(d.price),0) as price_total,
                COALESCE(SUM(d.passenger),0) as passengers_total,
                COALESCE(SUM(d.passage),0) as passage_total,
                COALESCE(SUM(COALESCE(d.passenger,0)*COALESCE(d.passage,0)),0) as total_pasaje_total
            ')
            ->first();

        return $row ?: (object)[
            'records'=>0,'times_total'=>0,'price_total'=>0,
            'passengers_total'=>0,'passage_total'=>0,'total_pasaje_total'=>0
        ];
    }
}
