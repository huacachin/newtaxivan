<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class DeparturesExport implements FromView, ShouldAutoSize, WithEvents, WithColumnWidths
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

    /* ========= Compactación base: fija A y B desde el inicio ========= */
    public function columnWidths(): array
    {
        // Estas se reafirman en AfterSheet; aquí ya dejas la base “al ras”.
        return [
            'A' => 2.2, // Id/Ítem muy angosto
            'B' => 9.0, // Placa o texto corto legible
            // C..M se rematan en AfterSheet
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();

                // Paleta
                $blueDark   = 'FF2874A6'; // encabezados
                $footerFill = 'FFCEE7FF'; // totales
                $white      = 'FFFFFFFF';
                $fontBlack  = 'FF000000';
                $red        = 'FFCC0000';
                $borderSoft = 'FFCFD8DC';

                // ===== Encabezado compacto (A1:M1) =====
                $s->mergeCells('A1:M1');
                $s->setCellValue('A1', 'LISTADO GENERAL DE SALIDA');
                $s->getStyle('A1:M1')->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$white]],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'=>Alignment::VERTICAL_CENTER,
                    ],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$red], 'size'=>10],
                ]);
                $s->getRowDimension(1)->setRowHeight(18);

                // ===== Reindex sin títulos de sección visibles =====
                $rSec1Title = 2;      // (oculto)
                $rSec1Hdr1  = 3;
                $rSec1Hdr2  = 4;
                $rSec1Body1 = 5;
                $rSec1BodyN = $rSec1Body1 + max(1, $this->countExisting) - 1;
                $rSec1Total = $rSec1BodyN + 1;
                $rBlank2    = $rSec1Total + 1;

                $rSec2Title = $rBlank2 + 1; // (oculto)
                $rSec2Hdr1  = $rSec2Title + 1;
                $rSec2Hdr2  = $rSec2Title + 2;
                $rSec2Body1 = $rSec2Hdr2 + 1;
                $rSec2BodyN = $rSec2Body1 + max(1, $this->countSupport) - 1;
                $rSec2Total = $rSec2BodyN + 1;
                $rBlank3    = $rSec2Total + 1;

                $rGrand     = $rBlank3 + 1;
                $lastRow    = $rGrand;

                // Ocultar títulos de sección
                foreach ([$rSec1Title, $rSec2Title] as $r) {
                    $s->mergeCells("A{$r}:M{$r}");
                    $s->setCellValue("A{$r}", '');
                    $s->getRowDimension($r)->setRowHeight(0);
                }

                // Cabeceras de 2 niveles
                $this->mergeHeader($s, $rSec1Hdr1, $rSec1Hdr2);
                $this->mergeHeader($s, $rSec2Hdr1, $rSec2Hdr2);

                // THEADs en azul, 10pt, compacto (wrap solo en cabeceras)
                foreach ([[$rSec1Hdr1,$rSec1Hdr2], [$rSec2Hdr1,$rSec2Hdr2]] as [$h1,$h2]) {
                    $s->getStyle("A{$h1}:M{$h2}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$white], 'size'=>10],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'=>Alignment::VERTICAL_CENTER,
                            'wrapText'=>true
                        ],
                    ]);
                    $s->getRowDimension($h1)->setRowHeight(18);
                    $s->getRowDimension($h2)->setRowHeight(16);
                }

                // Cuerpo “Apoyo” en rojo
                if ($this->countSupport > 0) {
                    $s->getStyle("A{$rSec2Body1}:M{$rSec2BodyN}")
                        ->getFont()->getColor()->setARGB($red);
                }

                // Totales por bloque
                foreach ([$rSec1Total, $rSec2Total] as $ft) {
                    $s->getStyle("A{$ft}:M{$ft}")->applyFromArray([
                        'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$footerFill]],
                        'font' => ['bold'=>true, 'color'=>['argb'=>$fontBlack], 'size'=>10],
                        'alignment' => [
                            'horizontal'=>Alignment::HORIZONTAL_CENTER,
                            'vertical'=>Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'outline' => [
                                'borderStyle'=>Border::BORDER_THIN,
                                'color'=>['argb'=>$blueDark]
                            ]
                        ]
                    ]);
                    $s->getRowDimension($ft)->setRowHeight(18);
                }

                // TOTAL GENERAL
                $s->getStyle("A{$rGrand}:M{$rGrand}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$footerFill]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontBlack], 'size'=>10],
                    'borders' => [
                        'outline' => [
                            'borderStyle'=>Border::BORDER_MEDIUM,
                            'color'=>['argb'=>$blueDark]
                        ]
                    ],
                ]);
                $s->getRowDimension($rGrand)->setRowHeight(18);

                // Bordes finos a toda la grilla
                $s->getStyle("A1:M{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => $borderSoft]
                        ]
                    ]
                ]);

                // Números a la derecha (H..M)
                foreach (['H','I','J','K','L','M'] as $col) {
                    $s->getStyle("{$col}{$rSec1Body1}:{$col}{$rSec1Total}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$col}{$rSec2Body1}:{$col}{$rSec2Total}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$col}{$rGrand}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ====== COMPACTACIÓN (reemplaza autosize por anchos fijos) ======
                // Altura por defecto compacta y sin wrap en cuerpo
                $s->getDefaultRowDimension()->setRowHeight(14);
                if ($this->countExisting > 0) {
                    $s->getStyle("A{$rSec1Body1}:M{$rSec1BodyN}")
                        ->getAlignment()->setWrapText(false);
                }
                if ($this->countSupport > 0) {
                    $s->getStyle("A{$rSec2Body1}:M{$rSec2BodyN}")
                        ->getAlignment()->setWrapText(false);
                }

                // 1) Desactivar autosize y poner un default MUY angosto a todas
                foreach (range('A','M') as $col) {
                    $s->getColumnDimension($col)->setAutoSize(false);
                    $s->getColumnDimension($col)->setWidth(3.0); // default global súper compacto
                    $s->getStyle("{$col}1:{$col}{$lastRow}")
                        ->getAlignment()->setIndent(0)->setShrinkToFit(true);
                }

                // 2) Overrides columna por columna (ajusta a tu gusto)
                //    A “al ras” para correlativo / id; B y C más legibles; D-E horas; H..M numéricos compactos.
                $s->getColumnDimension('A')->setWidth(3);
                $s->getColumnDimension('B')->setWidth(9.0);
                $s->getColumnDimension('C')->setWidth(10.0);
                $s->getColumnDimension('D')->setWidth(7.0);
                $s->getColumnDimension('E')->setWidth(7.0);
                $s->getColumnDimension('F')->setWidth(9.0);
                $s->getColumnDimension('G')->setWidth(9.0);
                $s->getColumnDimension('H')->setWidth(5.5);
                $s->getColumnDimension('I')->setWidth(4.0);
                $s->getColumnDimension('J')->setWidth(4.0);
                $s->getColumnDimension('K')->setWidth(4.0);
                $s->getColumnDimension('L')->setWidth(4.0);
                $s->getColumnDimension('M')->setWidth(4.0);

                // Si quieres aún más “al ras”, prueba:
                // A->2.0, D/E->6.5, H..M->7.5, etc.
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
