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
use PhpOffice\PhpSpreadsheet\RichText\RichText;

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

                // ===== Cálculo de rangos mínimos para cuerpo y totales =====
                // Tomamos los mismos contadores que setea view()
                $rSec1Body1 = 5;
                $rSec1BodyN = $rSec1Body1 + max(1, $this->countExisting) - 1;
                $rSec1Total = $rSec1BodyN + 1;

                $rSec2Body1 = $rSec1Total + 3; // fila en blanco + (título oculto si existiera) + cabeceras
                $rSec2BodyN = $rSec2Body1 + max(1, $this->countSupport) - 1;
                $rSec2Total = $rSec2BodyN + 1;

                $rGrand     = $rSec2Total + 2; // fila en blanco y total general
                $lastRow    = $rGrand;

                /* =========================
                 *  Ajuste Fino “Muy Pegado”
                 * ========================= */

                // 1) Altura por defecto compacta (ideal para font 10pt)
                $s->getDefaultRowDimension()->setRowHeight(14);

                // 2) Sin wrap en CUERPO para no inflar alturas
                if ($this->countExisting > 0) {
                    $s->getStyle("A{$rSec1Body1}:M{$rSec1BodyN}")
                        ->getAlignment()->setWrapText(false);
                }
                if ($this->countSupport > 0) {
                    $s->getStyle("A{$rSec2Body1}:M{$rSec2BodyN}")
                        ->getAlignment()->setWrapText(false);
                }

                // 3) Quitar indentaciones (sangría) en todo el rango útil
                $s->getStyle("A1:M{$lastRow}")
                    ->getAlignment()->setIndent(0);

                // 4) Autosize base
                foreach (range('A','M') as $col) {
                    $s->getColumnDimension($col)->setAutoSize(true);
                }

                // Forzar cálculo antes de leer anchos (best-effort)
                \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance(
                    $s->getParent()
                )->clearCalculationCache();

                // 5) Recorte fino del ancho para eliminar la “holgura” del autosize
                $trim = 0.8; // ajusta entre 0.6 y 1.0 si lo quieres aún más al ras
                foreach (range('A','M') as $col) {
                    $dim = $s->getColumnDimension($col);
                    $current = $dim->getWidth();
                    if ($current === null || $current <= 0 || $current === -1) {
                        $current = 8.0; // fallback razonable
                    }
                    $dim->setAutoSize(false);
                    $dim->setWidth(max(1.0, $current - $trim));
                }

                // (Opcional) Alinear números a la derecha si lo necesitas:
                // foreach (['H','I','J','K','L','M'] as $col) {
                //     $s->getStyle("{$col}{$rSec1Body1}:{$col}{$rSec1Total}")
                //       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                //     $s->getStyle("{$col}{$rSec2Body1}:{$col}{$rSec2Total}")
                //       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                //     $s->getStyle("{$col}{$rGrand}")
                //       ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                // }

                // Importante: No congelamos pane (sin freezePane) y no tocamos header.
            },
        ];
    }






    private function mergeHeader($sheet, int $r1, int $r2): void
    {
        foreach (['A','B','C','F','G'] as $col) {
            $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
        }
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
        elseif ($this->fromDate)                    $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                      $q->whereDate('d.date', '<=', $this->toDate);

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
        elseif ($this->fromDate)                    $q->whereDate('d.date', '>=', $this->fromDate);
        elseif ($this->toDate)                      $q->whereDate('d.date', '<=', $this->toDate);

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
