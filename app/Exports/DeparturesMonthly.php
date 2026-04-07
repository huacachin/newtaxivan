<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DeparturesMonthly implements FromArray, WithStyles, WithEvents
{
    public function __construct(
        protected int $year,
        protected int $month,
        protected string $dateColumn = 'date',
        protected ?string $countColumn = null
    ) {}

    protected int   $daysInMonth = 0;
    protected array $days = [];
    protected array $rows = [];
    protected array $totalPerDay = [];
    protected array $vehiclesWorkedPerDay = [];

    // Per HQ
    protected array $hqTables = [];
    protected array $hqSummary = [];
    protected int   $grandTotalVueltas = 0;
    protected int   $grandTotalVT = 0;

    // Section positions for styling
    protected array $sections = [];

    /* ----------------- Data ----------------- */

    public function array(): array
    {
        $this->prepare();

        $data = [];
        $currentRow = 1; // tracks Excel row (1-based)

        // ===== MAIN TABLE =====
        // Title row
        $titleText = 'REPORTE MENSUAL POR PLACA - V.T ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
        $titleRow = array_merge([$titleText], array_fill(0, $this->daysInMonth + 1, ''));
        $data[] = $titleRow;
        $this->sections['main_title'] = $currentRow;
        $currentRow++;

        // Header row
        $head = ['Nº', 'Placa'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $head[] = (string)$d;
        $head[] = 'T. Salida';
        $data[] = $head;
        $this->sections['main_header'] = $currentRow;
        $currentRow++;

        // Data rows
        $this->sections['main_data_start'] = $currentRow;
        $i = 0;
        foreach ($this->rows as $r) {
            $i++;
            $row = [$i, $r['plate']];
            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $row[] = (int)($r['daily'][$d] ?? 0);
            }
            $row[] = (int)$r['total'];
            $data[] = $row;
            $currentRow++;
        }
        $this->sections['main_data_end'] = $currentRow - 1;

        // Footer: Total Salidas
        $rowA = ['', 'Total Vueltas'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $rowA[] = (int)($this->totalPerDay[$d] ?? 0);
        $rowA[] = array_sum($this->totalPerDay);
        $data[] = $rowA;
        $this->sections['main_footer1'] = $currentRow;
        $currentRow++;

        // Footer: Total V.T.
        $rowB = ['', 'Total V.T.'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) $rowB[] = (int)($this->vehiclesWorkedPerDay[$d] ?? 0);
        $rowB[] = array_sum($this->vehiclesWorkedPerDay);
        $data[] = $rowB;
        $this->sections['main_footer2'] = $currentRow;
        $currentRow++;

        // ===== PER-HQ TABLES =====
        $this->sections['hq_sections'] = [];

        foreach ($this->hqTables as $hqId => $hq) {
            // Blank separator
            $data[] = array_fill(0, $this->daysInMonth + 3, '');
            $currentRow++;

            $sec = [];

            // HQ Title
            $hqTitle = mb_strtoupper($hq['name']) . ' - V.T ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
            $data[] = array_merge([$hqTitle], array_fill(0, $this->daysInMonth + 1, ''));
            $sec['title'] = $currentRow;
            $currentRow++;

            // HQ Header
            $data[] = $head; // same headers
            $sec['header'] = $currentRow;
            $currentRow++;

            // HQ Data
            $sec['data_start'] = $currentRow;
            $hi = 0;
            foreach ($hq['rows'] as $r) {
                $hi++;
                $row = [$hi, $r['plate']];
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $row[] = (int)($r['daily'][$d] ?? 0);
                }
                $row[] = (int)$r['total'];
                $data[] = $row;
                $currentRow++;
            }
            $sec['data_end'] = $currentRow - 1;

            // HQ Total Salidas
            $fA = ['', 'Total Vueltas'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) $fA[] = (int)($hq['totalPerDay'][$d] ?? 0);
            $fA[] = array_sum($hq['totalPerDay']);
            $data[] = $fA;
            $sec['footer1'] = $currentRow;
            $currentRow++;

            // HQ Total V.T.
            $fB = ['', 'Total V.T.'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) $fB[] = (int)($hq['vtPerDay'][$d] ?? 0);
            $fB[] = array_sum($hq['vtPerDay']);
            $data[] = $fB;
            $sec['footer2'] = $currentRow;
            $currentRow++;

            $this->sections['hq_sections'][] = $sec;
        }

        // ===== SUMMARY TABLE =====
        if (!empty($this->hqSummary)) {
            // Column splits: divide table width into 3 zones for the summary
            $totalCols = 2 + $this->daysInMonth + 1; // same as lastColIdx
            $sumC1End  = (int) floor($totalCols / 3);
            $sumC2Start = $sumC1End + 1;
            $sumC2End   = (int) floor(2 * $totalCols / 3);
            $sumC3Start = $sumC2End + 1;
            $sumC3End   = $totalCols;

            $this->sections['sum_cols'] = [
                'c1End'    => $sumC1End,
                'c2Start'  => $sumC2Start,
                'c2End'    => $sumC2End,
                'c3Start'  => $sumC3Start,
                'c3End'    => $sumC3End,
            ];

            // Helper: build a full-width row with values at zone starts
            $sumRow = function ($val1, $val2, $val3) use ($totalCols, $sumC2Start, $sumC3Start) {
                $row = array_fill(0, $totalCols, '');
                $row[0] = $val1;
                $row[$sumC2Start - 1] = $val2; // 0-based index
                $row[$sumC3Start - 1] = $val3;
                return $row;
            };

            // Blank separator
            $data[] = array_fill(0, $totalCols, '');
            $currentRow++;

            // Summary title
            $sumTitle = 'RESUMEN POR SEDE - ' . mb_strtoupper($this->monthName()) . ' ' . $this->year;
            $data[] = array_merge([$sumTitle], array_fill(0, $totalCols - 1, ''));
            $this->sections['sum_title'] = $currentRow;
            $currentRow++;

            // Summary header
            $data[] = $sumRow('Sede', 'Total Vueltas', 'Total V.T');
            $this->sections['sum_header'] = $currentRow;
            $currentRow++;

            // Summary rows
            $this->sections['sum_data_start'] = $currentRow;
            foreach ($this->hqSummary as $s) {
                $data[] = $sumRow($s['name'], $s['totalVueltas'], $s['totalVT']);
                $currentRow++;
            }
            $this->sections['sum_data_end'] = $currentRow - 1;

            // Summary total
            $data[] = $sumRow('TOTAL GENERAL', $this->grandTotalVueltas, $this->grandTotalVT);
            $this->sections['sum_footer'] = $currentRow;
            $currentRow++;

            // Disclaimer
            $data[] = array_merge(['* El Total General V.T no es la suma de los V.T por sede. Un vehiculo que opera en varias sedes el mismo dia se cuenta una sola vez en el total general.'], array_fill(0, $totalCols - 1, ''));
            $this->sections['disclaimer'] = $currentRow;
        }

        return $data;
    }

    public function styles(Worksheet $sheet) { return []; }

    /* ----------------- AfterSheet: styling ----------------- */

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $red      = 'FFF80000';
                $redBg    = 'FFFF0000';

                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(15);
                $ws->setShowGridLines(false);

                $lastColIdx    = 2 + $this->daysInMonth + 1; // Item, Placa, days, T.Salida
                $lastCol       = Coordinate::stringFromColumnIndex($lastColIdx);
                $firstDayColIdx = 3; // C = day 1

                // Sunday column indices
                $sundayCols = [];
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    if (Carbon::create($this->year, $this->month, $d)->isSunday()) {
                        $sundayCols[] = $firstDayColIdx + ($d - 1);
                    }
                }

                // Helper: style a full table section
                $styleSection = function (array $sec) use ($ws, $blue, $white, $black, $red, $redBg, $footerBg, $lastCol, $lastColIdx, $firstDayColIdx, $sundayCols) {
                    // Title
                    $titleRow = $sec['title'];
                    $ws->mergeCells("A{$titleRow}:{$lastCol}{$titleRow}");
                    $ws->getStyle("A{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                    $ws->getRowDimension($titleRow)->setRowHeight(20);

                    // Header
                    $headerRow = $sec['header'];
                    $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    ]);
                    $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                    $ws->getRowDimension($headerRow)->setRowHeight(18);

                    // Sundays in header
                    foreach ($sundayCols as $colIdx) {
                        $col = Coordinate::stringFromColumnIndex($colIdx);
                        $ws->getStyle("{$col}{$headerRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $redBg]],
                            'font' => ['bold' => true, 'color' => ['argb' => $white]],
                        ]);
                    }

                    // Data rows: dashed horizontal, solid vertical
                    $dataStart = $sec['data_start'];
                    $dataEnd   = $sec['data_end'];
                    if ($dataEnd >= $dataStart) {
                        $dataRange = "A{$dataStart}:{$lastCol}{$dataEnd}";
                        $borders   = $ws->getStyle($dataRange)->getBorders();
                        $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($black);
                        $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);

                        $ws->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                        $ws->getStyle("A{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $cCol = Coordinate::stringFromColumnIndex($firstDayColIdx);
                        $ws->getStyle("{$cCol}{$dataStart}:{$lastCol}{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $ws->getStyle("{$cCol}{$dataStart}:{$lastCol}{$dataEnd}")->getNumberFormat()->setFormatCode('0');

                        // Fill empty cells with 0
                        for ($r = $dataStart; $r <= $dataEnd; $r++) {
                            for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                                $cell = $ws->getCell(Coordinate::stringFromColumnIndex($c) . $r);
                                if ($cell->getValue() === null || $cell->getValue() === '') {
                                    $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                                }
                            }
                        }
                    }

                    // Footers
                    foreach ([$sec['footer1'], $sec['footer2']] as $fr) {
                        $ws->getStyle("A{$fr}:{$lastCol}{$fr}")->applyFromArray([
                            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                        ]);
                        $ws->getStyle("A{$fr}:B{$fr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $cCol = Coordinate::stringFromColumnIndex($firstDayColIdx);
                        $ws->getStyle("{$cCol}{$fr}:{$lastCol}{$fr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $ws->getStyle("{$cCol}{$fr}:{$lastCol}{$fr}")->getNumberFormat()->setFormatCode('0');
                        $ws->getRowDimension($fr)->setRowHeight(18);
                    }
                };

                // ===== Style main table =====
                $styleSection([
                    'title'      => $this->sections['main_title'],
                    'header'     => $this->sections['main_header'],
                    'data_start' => $this->sections['main_data_start'],
                    'data_end'   => $this->sections['main_data_end'],
                    'footer1'    => $this->sections['main_footer1'],
                    'footer2'    => $this->sections['main_footer2'],
                ]);

                // Freeze pane on main table
                // $ws->freezePane(...); // removido

                // ===== Style per-HQ tables =====
                foreach ($this->sections['hq_sections'] as $sec) {
                    $styleSection($sec);
                }

                // ===== Style summary table =====
                if (isset($this->sections['sum_title'])) {
                    $sc = $this->sections['sum_cols'];
                    $sc1End  = Coordinate::stringFromColumnIndex($sc['c1End']);
                    $sc2Start = Coordinate::stringFromColumnIndex($sc['c2Start']);
                    $sc2End   = Coordinate::stringFromColumnIndex($sc['c2End']);
                    $sc3Start = Coordinate::stringFromColumnIndex($sc['c3Start']);
                    $sc3End   = Coordinate::stringFromColumnIndex($sc['c3End']);

                    $sumTitleRow  = $this->sections['sum_title'];
                    $sumHeaderRow = $this->sections['sum_header'];
                    $sumDataStart = $this->sections['sum_data_start'];
                    $sumDataEnd   = $this->sections['sum_data_end'];
                    $sumFooterRow = $this->sections['sum_footer'];

                    // Helper: merge 3 zones for a given row
                    $mergeSumRow = function (int $row) use ($ws, $sc1End, $sc2Start, $sc2End, $sc3Start, $sc3End) {
                        $ws->mergeCells("A{$row}:{$sc1End}{$row}");
                        $ws->mergeCells("{$sc2Start}{$row}:{$sc2End}{$row}");
                        $ws->mergeCells("{$sc3Start}{$row}:{$sc3End}{$row}");
                    };

                    // Title: merge full width
                    $ws->mergeCells("A{$sumTitleRow}:{$sc3End}{$sumTitleRow}");
                    $ws->getStyle("A{$sumTitleRow}:{$sc3End}{$sumTitleRow}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                    $ws->getRowDimension($sumTitleRow)->setRowHeight(20);

                    // Header: merge 3 zones
                    $mergeSumRow($sumHeaderRow);
                    $ws->getStyle("A{$sumHeaderRow}:{$sc3End}{$sumHeaderRow}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                    $ws->getRowDimension($sumHeaderRow)->setRowHeight(18);

                    // Data rows: merge zones + dashed horizontal borders
                    if ($sumDataEnd >= $sumDataStart) {
                        for ($r = $sumDataStart; $r <= $sumDataEnd; $r++) {
                            $mergeSumRow($r);
                        }
                        $dataRange = "A{$sumDataStart}:{$sc3End}{$sumDataEnd}";
                        $borders   = $ws->getStyle($dataRange)->getBorders();
                        $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
                        $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($black);
                        $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);

                        $ws->getStyle("{$sc2Start}{$sumDataStart}:{$sc3End}{$sumDataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Footer: merge zones
                    $mergeSumRow($sumFooterRow);
                    $ws->getStyle("A{$sumFooterRow}:{$sc3End}{$sumFooterRow}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                    $ws->getRowDimension($sumFooterRow)->setRowHeight(18);

                    // Disclaimer: merge full width
                    if (isset($this->sections['disclaimer'])) {
                        $discRow = $this->sections['disclaimer'];
                        $ws->mergeCells("A{$discRow}:{$sc3End}{$discRow}");
                        $ws->getStyle("A{$discRow}:{$sc3End}{$discRow}")->applyFromArray([
                            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF808080']],
                            'alignment' => ['wrapText' => true],
                        ]);
                    }
                }

                // ===== Column widths: autoSize for days and totals =====
                $ws->getColumnDimension('A')->setAutoSize(false)->setWidth(6.0);
                $ws->getColumnDimension('B')->setAutoSize(false)->setWidth(9.5);
                for ($c = $firstDayColIdx; $c <= $lastColIdx; $c++) {
                    $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
                }

                // Hide extra columns
                for ($c = $lastColIdx + 1; $c <= 50; $c++) {
                    $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setVisible(false);
                }

                // Global font & no wrap (except disclaimer)
                $finalRow = (int) $ws->getHighestRow();
                $ws->getStyle("A1:{$lastCol}{$finalRow}")->getFont()->setSize(10);
            },
        ];
    }

    /* ----------------- Data & Helpers ----------------- */

    protected function prepare(): void
    {
        if ($this->daysInMonth > 0) return;

        if (!Schema::hasColumn('departures', $this->dateColumn)) {
            $this->dateColumn = 'date';
        }
        if ($this->countColumn === null) {
            foreach (['laps','num','quantity','vueltas','count','total_turns','times'] as $c) {
                if (Schema::hasColumn('departures', $c)) { $this->countColumn = $c; break; }
            }
        }

        $start = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $this->daysInMonth = (int) $start->daysInMonth;
        $this->days        = range(1, $this->daysInMonth);

        // 1) Active vehicles in order (same as Livewire view)
        $vehCols = ['id', 'plate'];
        if (Schema::hasColumn('vehicles', 'sort_order')) $vehCols[] = 'sort_order';

        $vehQ = DB::table('vehicles')->select($vehCols);
        if (Schema::hasColumn('vehicles', 'status')) {
            $vehQ->where('status', 'active');
        }
        $vehicles = $vehQ
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('plate')
            ->get();

        $this->rows = [];
        foreach ($vehicles as $v) {
            $this->rows[(int)$v->id] = [
                'sort_order' => (string)($v->sort_order ?? ''),
                'plate'      => (string)$v->plate,
                'daily'      => array_fill(1, $this->daysInMonth, 0),
                'total'      => 0,
            ];
        }

        // 2) Aggregates
        $dateCol   = $this->dateColumn;
        $selectRaw = $this->countColumn
            ? "vehicle_id, DAY({$dateCol}) as d, SUM({$this->countColumn}) as s"
            : "vehicle_id, DAY({$dateCol}) as d, COUNT(*) as s";

        $aggs = DB::table('departures')
            ->selectRaw($selectRaw)
            ->whereBetween($dateCol, [$start->toDateString(), $end->toDateString()])
            ->groupBy('vehicle_id', 'd')
            ->get();

        foreach ($aggs as $r) {
            $vid = (int) $r->vehicle_id;
            $d   = (int) $r->d;
            $s   = (float) $r->s;
            if (!isset($this->rows[$vid])) continue;
            $this->rows[$vid]['daily'][$d] = (int) round($s / 2, 0, PHP_ROUND_HALF_UP);
        }

        // 3) Totals
        $this->totalPerDay = array_fill(1, $this->daysInMonth, 0);
        foreach ($this->rows as &$row) {
            $row['total'] = array_sum($row['daily']);
            foreach ($row['daily'] as $d => $val) $this->totalPerDay[$d] += $val;
        }
        unset($row);

        // 4) Vehicles worked per day
        $this->vehiclesWorkedPerDay = array_fill(1, $this->daysInMonth, 0);
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $worked = 0;
            foreach ($this->rows as $row) if (($row['daily'][$d] ?? 0) > 0) $worked++;
            $this->vehiclesWorkedPerDay[$d] = $worked;
        }

        // 5) Per-HQ data
        $this->prepareByHQ($start->toDateString(), $end->toDateString(), $vehicles);
    }

    protected function prepareByHQ(string $startDate, string $endDate, $vehicles): void
    {
        $this->hqTables = [];
        $this->hqSummary = [];
        $this->grandTotalVueltas = 0;
        $this->grandTotalVT = 0;

        $dateCol = $this->dateColumn;

        // Apoyo: departures con is_support=1, agrupados por sede/placa/día
        $supportAggs = DB::table('departures as d')
            ->join('headquarters as h', 'h.id', '=', 'd.headquarter_id')
            ->selectRaw("d.headquarter_id, h.name as hq_name, d.legacy_plate as plate, DAY(d.{$dateCol}) as day_num, SUM(d.times) as raw_sum")
            ->where('d.is_support', 1)
            ->whereBetween("d.{$dateCol}", [$startDate, $endDate])
            ->groupBy('d.headquarter_id', 'h.name', 'd.legacy_plate', 'day_num')
            ->get();

        // Indexar: [hq_id => ['name'=>..., 'plates'=>[plate => [d => raw]]]]
        $byHQ = [];
        foreach ($supportAggs as $r) {
            $hqId = (int)$r->headquarter_id;
            $byHQ[$hqId]['name'] = $r->hq_name;
            $byHQ[$hqId]['plates'][$r->plate][(int)$r->day_num] =
                ($byHQ[$hqId]['plates'][$r->plate][(int)$r->day_num] ?? 0) + (int)$r->raw_sum;
        }

        foreach ($byHQ as $hqId => $hqData) {
            $hqRows = [];
            $totalPerDay = array_fill(1, $this->daysInMonth, 0);
            $vtPerDay    = array_fill(1, $this->daysInMonth, 0);
            $item = 0;

            foreach ($hqData['plates'] as $plate => $days) {
                $item++;
                $daily = array_fill(1, $this->daysInMonth, 0);

                foreach ($days as $d => $raw) {
                    if ($d < 1 || $d > $this->daysInMonth) continue;
                    $daily[$d] = (int)$raw;
                }

                $total = array_sum($daily);
                if ($total === 0) continue;

                $hqRows[] = [
                    'plate' => (string)$plate,
                    'daily' => $daily,
                    'total' => $total,
                ];

                foreach ($daily as $d => $val) {
                    $totalPerDay[$d] += $val;
                    if ($val > 0) $vtPerDay[$d]++;
                }
            }

            if (empty($hqRows)) continue;

            // Ordenar por total de salidas descendente
            usort($hqRows, fn($a, $b) => $b['total'] <=> $a['total']);

            $this->hqTables[$hqId] = [
                'name'        => $hqData['name'],
                'rows'        => $hqRows,
                'totalPerDay' => $totalPerDay,
                'vtPerDay'    => $vtPerDay,
            ];

            $totalVueltas = array_sum($totalPerDay);
            $totalVT      = array_sum($vtPerDay);

            $this->hqSummary[$hqId] = [
                'name'          => $hqData['name'],
                'totalVueltas'  => $totalVueltas,
                'totalVT'       => $totalVT,
            ];

            $this->grandTotalVueltas += $totalVueltas;
        }

        $this->grandTotalVT = array_sum($this->vehiclesWorkedPerDay);
    }

    public function htmlData(): array
    {
        $this->prepare();

        $sundays = [];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $sundays[$d] = Carbon::create($this->year, $this->month, $d)->isSunday();
        }

        return [
            'year'                 => $this->year,
            'month'                => $this->month,
            'monthName'            => $this->monthName(),
            'daysInMonth'          => $this->daysInMonth,
            'sundays'              => $sundays,
            'rows'                 => $this->rows,
            'totalPerDay'          => $this->totalPerDay,
            'vehiclesWorkedPerDay' => $this->vehiclesWorkedPerDay,
            'hqTables'             => $this->hqTables,
            'hqSummary'            => $this->hqSummary,
            'grandTotalVueltas'    => $this->grandTotalVueltas,
            'grandTotalVT'         => $this->grandTotalVT,
        ];
    }

    protected function monthName(): string
    {
        $m = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        return $m[$this->month] ?? '';
    }
}
