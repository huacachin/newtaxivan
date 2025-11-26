<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DeparturesExport implements FromView, ShouldAutoSize, WithColumnWidths, WithTitle, WithEvents
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

        // Para calcular las filas en AfterSheet
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

        return view('exports.departures', compact(
            'rows',
            'totals',
            'supportRows',
            'supTotals',
            'grand',
            'filters'
        ));
    }

    /* ========= Anchos de columnas ========= */
    public function columnWidths(): array
    {
        return [
            'A' => 4,
            'B' => 8,
            'C' => 11,
            'D' => 9,
            'E' => 8,
            'F' => 10,
            'G' => 7,
            'H' => 7,
            'I' => 5,
            'J' => 6,
            'K' => 5,
            'L' => 5,
            'M' => 9,
        ];
    }

    /* ========= Título de la pestaña: SALIDAS + FECHA ========= */
    public function title(): string
    {
        if ($this->fromDate && $this->toDate && $this->fromDate !== $this->toDate) {
            $dateLabel = $this->fromDate . ' al ' . $this->toDate;
        } else {
            $dateLabel = $this->fromDate
                ?? $this->toDate
                ?? now()->format('Y-m-d');
        }

        return mb_substr('SALIDAS ' . $dateLabel, 0, 31);
    }

    /* ========= Colores y estilos (sin merges) ========= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // Paleta
                $blueDark   = 'FF2874A6';
                $footerFill = 'FFCEE7FF';
                $white      = 'FFFFFFFF';
                $red        = 'F80000';
                $black      = 'FF000000';
                $borderSoft = '000000'; // gris suave para bordes

                // n1/n2: cantidad de filas de cuerpo por sección (mínimo 1 por "Sin datos")
                $n1 = max(1, $this->countExisting);
                $n2 = max(1, $this->countSupport);

                // ===== Mapeo de filas según el Blade =====
                // Row 1: título
                // Sección 1
                $rSec1Hdr1  = 2;                  // header 1
                $rSec1Hdr2  = 3;                  // header 2
                $rSec1Body1 = 4;                  // primer body
                $rSec1BodyN = 3 + $n1;            // 4 + n1 - 1
                $rSec1Total = 4 + $n1;            // fila TOTAL sección 1

                // Sección 2
                $rSec2Title = $rSec1Total + 1;    // "V.APOYO"
                $rSec2Hdr1  = $rSec2Title + 1;    // header 1
                $rSec2Hdr2  = $rSec2Title + 2;    // header 2
                $rSec2Body1 = $rSec2Hdr2 + 1;     // primer body
                $rSec2BodyN = $rSec2Body1 + $n2 - 1;
                $rSec2Total = $rSec2BodyN + 1;    // TOTAL sección 2

                // TOTAL GENERAL
                $rGrand     = $rSec2Total + 1;
                $lastRow    = $rGrand;

                // ===== Título (fila 1) =====
                $s->getStyle('A1:M1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => $red],
                        'size' => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // ===== Encabezados (sección 1 y 2) =====
                foreach ([[$rSec1Hdr1, $rSec1Hdr2], [$rSec2Hdr1, $rSec2Hdr2]] as [$h1, $h2]) {
                    $s->getStyle("A{$h1}:M{$h2}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $blueDark],
                        ],
                        'font' => [
                            'bold'  => true,
                            'color' => ['argb' => $white],
                            'size'  => 10,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true,
                        ],
                    ]);
                    $s->getRowDimension($h1)->setRowHeight(18);
                    $s->getRowDimension($h2)->setRowHeight(16);
                }

                // ===== Título V.APOYO =====
                $s->getStyle("A{$rSec2Title}:M{$rSec2Title}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['argb' => $red],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // ===== Cuerpo de V.APOYO en rojo =====
                if ($this->countSupport > 0) {
                    $s->getStyle("A{$rSec2Body1}:M{$rSec2BodyN}")
                        ->getFont()->getColor()->setARGB($red);
                }

                // ===== Totales (sección 1, sección 2) =====
                foreach ([$rSec1Total, $rSec2Total] as $ft) {
                    $s->getStyle("A{$ft}:M{$ft}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $footerFill],
                        ],
                        'font' => [
                            'bold'  => true,
                            'color' => ['argb' => $black],
                            'size'  => 10,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // ===== TOTAL GENERAL =====
                $s->getStyle("A{$rGrand}:M{$rGrand}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $footerFill],
                    ],
                    'font' => [
                        'bold'  => true,
                        'color' => ['argb' => $black],
                        'size'  => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // ===== Números a la derecha (H..M) =====
                foreach (['H', 'I', 'J', 'K', 'L', 'M'] as $col) {
                    // sección 1: body + total
                    $s->getStyle("{$col}{$rSec1Body1}:{$col}{$rSec1Total}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // sección 2: body + total
                    $s->getStyle("{$col}{$rSec2Body1}:{$col}{$rSec2Total}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // total general
                    $s->getStyle("{$col}{$rGrand}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Altura base
                $s->getDefaultRowDimension()->setRowHeight(14);

                // ===== BORDES a toda la grilla =====
                $s->getStyle("A1:M{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => $borderSoft],
                        ],
                    ],
                ]);

                // 🔹 Forzar tamaño de fuente 10 en TODO el rango usado (A..M)
                $s->getStyle("A1:M{$lastRow}")
                    ->getFont()
                    ->setSize(10);
            },
        ];
    }




    // =====================  QUERIES  =====================

    private function baseExisting()
    {
        $q = DB::table('departures as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->where('v.status', 'active');

        // Restricción por rol (controller -> solo sus registros)
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controller')) {
            $q->where('d.user_id', $user->id);
        }

        // Fechas
        if ($this->fromDate && $this->toDate) {
            $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        } elseif ($this->fromDate) {
            $q->whereDate('d.date', '>=', $this->fromDate);
        } elseif ($this->toDate) {
            $q->whereDate('d.date', '<=', $this->toDate);
        }

        // Búsqueda
        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ((int)$this->searchType) {
                case 1: // Placa
                    $q->where('v.plate', 'like', '%' . strtoupper($term) . '%');
                    break;
                case 2: // Usuario
                    $q->where('u.name', 'like', '%' . $term . '%');
                    break;
                case 3: // Sucursal
                    if (is_numeric($term)) {
                        $q->where('h.id', (int)$term);
                    } else {
                        $q->where('h.name', 'like', '%' . $term . '%');
                    }
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

        // Restricción por rol (controller -> solo sus registros)
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controller')) {
            $q->where('d.user_id', $user->id);
        }

        // Fechas
        if ($this->fromDate && $this->toDate) {
            $q->whereBetween('d.date', [$this->fromDate, $this->toDate]);
        } elseif ($this->fromDate) {
            $q->whereDate('d.date', '>=', $this->fromDate);
        } elseif ($this->toDate) {
            $q->whereDate('d.date', '<=', $this->toDate);
        }

        // Búsqueda
        $term = trim((string)($this->searchText ?? ''));
        if ($term !== '') {
            switch ((int)$this->searchType) {
                case 1: // Placa (legacy_plate)
                    $q->where('d.legacy_plate', 'like', '%' . strtoupper($term) . '%');
                    break;
                case 2: // Usuario
                    $q->where('u.name', 'like', '%' . $term . '%');
                    break;
                case 3: // Sucursal
                    if (is_numeric($term)) {
                        $q->where('h.id', (int)$term);
                    } else {
                        $q->where('h.name', 'like', '%' . $term . '%');
                    }
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
            ->orderBy('x.date')
            ->orderBy('x.hour')
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
            ->orderBy('x.date')
            ->orderBy('x.hour')
            ->get();

        $tot = $this->totalsFor($this->baseSupport());
        return [$rows, $tot];
    }

    private function totalsFor($base): object
    {
        $row = $base->cloneWithout(['orders', 'columns'])
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
            'records'            => 0,
            'times_total'        => 0,
            'price_total'        => 0,
            'passengers_total'   => 0,
            'passage_total'      => 0,
            'total_pasaje_total' => 0,
        ];
    }
}
