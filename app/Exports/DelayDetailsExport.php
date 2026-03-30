<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DelayDetailsExport implements FromArray, WithHeadings, WithEvents, WithTitle
{
    public function __construct(
        protected string  $monthDate,
        protected bool    $onlyActive   = true,
        protected string  $condition    = '',
        protected ?string $plateFilter  = null,
        protected array   $excludeHeads = ['Huachipa','Lima']
    ) {}

    private int $rowCount = 0;

    /* ---------------- datos ---------------- */
    public function array(): array
    {
        [$from, $toMonthEnd] = $this->monthBoundaries($this->monthDate);
        $seed   = Carbon::parse($this->monthDate);

        $vehiclesQ = DB::table('vehicles as v')
            ->select('v.id','v.plate','v.sort_order','v.condition','v.status');
        if ($this->onlyActive)       $vehiclesQ->where('v.status', 'active');
        if ($this->condition !== '') $vehiclesQ->where('v.condition', $this->condition);
        if ($this->plateFilter)      $vehiclesQ->where('v.plate', 'like', '%'.strtoupper($this->plateFilter).'%');

        $vehicles = $vehiclesQ
            ->orderByRaw('COALESCE(v.sort_order, 999999)')
            ->orderBy('v.plate')
            ->get();

        if ($vehicles->isEmpty()) { $this->rowCount = 0; return []; }

        $vehicleIds = $vehicles->pluck('id')->all();
        $days = $this->makeDays($from, $toMonthEnd);

        $costs = DB::table('cost_per_plate_days as c')
            ->select('c.vehicle_id','c.date', DB::raw('SUM(c.amount) as amount'))
            ->whereIn('c.vehicle_id', $vehicleIds)
            ->whereBetween('c.date', [$from, $toMonthEnd])
            ->groupBy('c.vehicle_id','c.date')
            ->get();
        $costMap = [];
        foreach ($costs as $c) $costMap[$c->vehicle_id][$c->date] = (float) $c->amount;

        [$amountCol, $dateCol] = $this->detectPaymentColumns();
        $payRows = DB::table('payments as p')
            ->select('p.vehicle_id', DB::raw("DATE(p.$dateCol) as d"),
                DB::raw("COUNT(*) as cnt"), DB::raw("COALESCE(SUM(p.$amountCol),0) as amt"))
            ->whereIn('p.vehicle_id', $vehicleIds)
            ->whereBetween(DB::raw("DATE(p.$dateCol)"), [$from, $toMonthEnd])
            ->where('p.type', '<>', 'DEUDA')
            ->groupBy('p.vehicle_id', DB::raw("DATE(p.$dateCol)"))
            ->get();
        $payMap = [];
        foreach ($payRows as $r) $payMap[$r->vehicle_id][$r->d] = ['cnt'=>(int)$r->cnt,'amt'=>(float)$r->amt];

        $deps = DB::table('departures as d')
            ->leftJoin('headquarters as h','h.id','=','d.headquarter_id')
            ->select('d.vehicle_id','d.date', DB::raw('SUM(d.times) as k1'))
            ->whereIn('d.vehicle_id', $vehicleIds)
            ->whereBetween('d.date', [$from, $toMonthEnd])
            ->when(!empty($this->excludeHeads), function ($q) {
                $q->where(function($qq){ $qq->whereNull('h.name')->orWhereNotIn('h.name', $this->excludeHeads); });
            })
            ->groupBy('d.vehicle_id','d.date')
            ->get();
        $depMap = [];
        foreach ($deps as $d) $depMap[$d->vehicle_id][$d->date] = (int)$d->k1;

        $data = [];
        $item = 0;
        foreach ($vehicles as $v) {
            if (Str::startsWith((string)$v->condition, 'EX')) continue;

            foreach ($days as $d) {
                $date = $d['d'];
                if ($d['isSunday']) continue;

                $cost = $date <= '2023-04-30' ? 10.00 : (float)($costMap[$v->id][$date] ?? 0.00);
                $cnt = (int)($payMap[$v->id][$date]['cnt'] ?? 0);
                $sum = (float)($payMap[$v->id][$date]['amt'] ?? 0.00);
                $expected = $cnt > 1 ? ($cost * $cnt) : $cost;

                if (round($sum,2) !== round($expected,2)) {
                    $k1 = (int)($depMap[$v->id][$date] ?? 0);
                    if ($k1 > 0) {
                        $item++;
                        $data[] = [
                            'item'   => $item,
                            'cod'    => $v->sort_order ?? '',
                            'fecha'  => $date,
                            'placa'  => $v->plate,
                            'vuelta' => (int)ceil($k1/2),
                            'monto'  => $expected,
                        ];
                    }
                }
            }
        }

        $this->rowCount = count($data);
        return $data;
    }

    public function headings(): array
    {
        return ['Item','Codigo','Fecha','Placa','Vueltas','S/'];
    }

public function title(): string
    {
        return "Deuda Detalle";
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Fuente 10pt global, sin gridlines
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->setShowGridlines(false);

                // Insertar SOLO 1 fila (título). No hay subtítulo ni AutoFilter.
                $ws->insertNewRowBefore(1, 1);

                $headerRow     = 2;              // cabecera real
                $dataStartRow  = 3;              // datos
                $lastRow       = $dataStartRow + max(0, $this->rowCount) - 1;
                $lastColLetter = 'F';            // A..F

                // ===== TÍTULO =====
                $seed      = Carbon::parse($this->monthDate);
                $monthText = mb_strtoupper($seed->locale('es')->translatedFormat('F')) . ' ' . $seed->year;
                $ws->setCellValue('A1', "REPORTE DE RETRASO  – {$monthText}");
                $ws->mergeCells("A1:{$lastColLetter}1");
                $ws->getRowDimension(1)->setRowHeight(22);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'F80000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);

                // ===== THEAD (azul #2874A6) =====
                $ws->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2874A6']],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // Congelar debajo del thead
                $ws->freezePane("A{$dataStartRow}");

                // ===== Anchos =====
                $ws->getColumnDimension('A')->setWidth(7.3);   // Item
                $ws->getColumnDimension('B')->setWidth(9);     // Codigo
                $ws->getColumnDimension('C')->setWidth(15.2);  // Fecha
                $ws->getColumnDimension('D')->setWidth(10.5);  // Placa
                $ws->getColumnDimension('E')->setWidth(10.3);  // Vueltas
                $ws->getColumnDimension('F')->setWidth(14);    // S/

                // ===== Bordes datos: dashed horizontal, thin vertical =====
                if ($lastRow >= $dataStartRow) {
                    $dataRange = "A{$dataStartRow}:{$lastColLetter}{$lastRow}";
                    $borders = $ws->getStyle($dataRange)->getBorders();
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setRGB('000000');
                    $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
                }

                // Datos: centrado + formato
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:{$lastColLetter}{$lastRow}")->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $ws->getStyle("C{$dataStartRow}:C{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    $ws->getStyle("F{$dataStartRow}:F{$lastRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                    // Alternar color de texto por placa (columna D)
                    $prevPlate = null;
                    $useRed = false;
                    for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                        $plate = (string) $ws->getCell("D{$r}")->getValue();
                        if ($prevPlate !== null && $plate !== $prevPlate) {
                            $useRed = !$useRed;
                        }
                        $color = $useRed ? 'FF0000' : '000000';
                        $ws->getStyle("A{$r}:{$lastColLetter}{$r}")->getFont()->getColor()->setRGB($color);
                        $prevPlate = $plate;
                    }
                }

                // ===== Footer (celeste #CEE7FF, texto negro) =====
                $totalRow = ($lastRow >= $dataStartRow) ? $lastRow + 1 : $headerRow + 1;
                $ws->mergeCells("A{$totalRow}:E{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'Total');
                $ws->setCellValue("F{$totalRow}", $lastRow >= $dataStartRow ? "=SUM(F{$dataStartRow}:F{$lastRow})" : 0);

                $ws->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CEE7FF']],
                    'borders'   => [
                        'top'      => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'bottom'   => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'left'     => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'right'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'vertical' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                    ],
                ]);
                $ws->getStyle("F{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
            },
        ];
    }

    /* ---------------- helpers ---------------- */
    private function monthBoundaries(string $anyDay): array
    {
        $d = Carbon::parse($anyDay)->startOfMonth();
        return [$d->toDateString(), $d->copy()->endOfMonth()->toDateString()];
    }

    private function makeDays(string $from, string $to): array
    {
        $days = [];
        $c = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($c->lte($end)) {
            $days[] = ['d' => $c->toDateString(), 'isSunday' => $c->dayOfWeekIso === 7];
            $c->addDay();
        }
        return $days;
    }

    private function detectPaymentColumns(): array
    {
        $cols = Schema::getColumnListing('payments');
        $amountCandidates = ['amount','total','total_amount','importe','import','price','value','amount_total'];
        $dateCandidates   = ['date_payment','date_register','fechac','fecha'];
        $amountCol = collect($amountCandidates)->first(fn($c) => in_array($c, $cols, true)) ?? 'amount';
        $dateCol   = collect($dateCandidates)->first(fn($c) => in_array($c, $cols, true)) ?? 'date_payment';
        return [$amountCol, $dateCol];
    }
}
