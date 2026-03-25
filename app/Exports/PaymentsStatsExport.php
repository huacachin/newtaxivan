<?php

namespace App\Exports;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PaymentsStatsExport implements FromArray, WithHeadings, WithEvents, WithTitle
{
    protected int $year;
    protected int $month;
    protected int $daysInMonth;

    // cache
    protected array $rows = [];
    protected array $footer = [];

    // tablas/columnas
    protected string $paymentsTable = 'payments';
    protected string $usersTable    = 'users';
    protected string $hqTable       = 'headquarters';
    protected string $amountCol     = 'amount';

    public function __construct(int $year, int $month)
    {
        $this->year  = $year;
        $this->month = $month;
        $this->daysInMonth = (int) CarbonImmutable::create($year, $month, 1)->daysInMonth;

        $this->build();
    }

    public function title(): string
    {
        return "Estadístico {$this->month}-{$this->year}";
    }

    public function headings(): array
    {
        $days = range(1, $this->daysInMonth);

        return array_merge(
            ['CONTROLADOR', 'PARADERO', 'TIPO'],
            array_map(fn($d) => (string)$d, $days),
            ['TOTAL']
        );
    }

    public function array(): array
    {
        return array_merge($this->rows, [$this->footer]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ======= Paleta =======
                $blueDark  = 'FF2874A6';
                $footerBg  = 'FFCEE7FF';
                $white     = 'FFFFFFFF';
                $black     = 'FF000000';
                $borderC   = 'FF000000';
                $sundayRed = 'FFF80000';
                $teal      = 'FF3E9281';

                $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $sheet->setShowGridlines(false);

                // Rango actual (antes de insertar título)
                $lastRow    = (int) $sheet->getHighestRow();
                $lastColIdx = 3 + $this->daysInMonth + 1; // A,B,C + days + Total
                $endCol     = Coordinate::stringFromColumnIndex($lastColIdx);

                // ======= Insertar título en fila 1 =======
                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells("A1:{$endCol}1");
                $sheet->setCellValue('A1', "REPORTE ESTADISTICO DE PAGO - " . mb_strtoupper($this->mesTexto($this->month)) . " {$this->year}");
                $sheet->getStyle("A1:{$endCol}1")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $white]],
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => $sundayRed]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(18);

                // ======= THEAD (fila 2) =======
                $sheet->getStyle("A2:{$endCol}2")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blueDark]],
                    'font' => ['bold' => true, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // Domingos en rojo (solo header)
                $monthStart = CarbonImmutable::create($this->year, $this->month, 1);
                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    if ($monthStart->day($d)->isSunday()) {
                        $col = Coordinate::stringFromColumnIndex(3 + $d);
                        $sheet->getStyle("{$col}2")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $sundayRed]],
                            'font' => ['bold' => true, 'color' => ['argb' => $white]],
                        ]);
                    }
                }

                // Congelar
                $sheet->freezePane('D3');

                // ======= Posiciones =======
                $firstDataRow   = 3;
                $footerExcelRow = $lastRow + 1; // +1 por título insertado
                $dataEndRow     = $footerExcelRow - 1;
                $firstDayCol    = Coordinate::stringFromColumnIndex(4); // D

                // ======= Data borders: dashed horizontal, thin vertical =======
                if ($dataEndRow >= $firstDataRow) {
                    $dataRange = "A{$firstDataRow}:{$endCol}{$dataEndRow}";
                    $borders = $sheet->getStyle($dataRange)->getBorders();
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB($borderC);
                    $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($borderC);
                }

                // ======= Data font color: negro =======
                if ($dataEndRow >= $firstDataRow) {
                    $sheet->getStyle("{$firstDayCol}{$firstDataRow}:{$endCol}{$dataEndRow}")
                        ->getFont()->getColor()->setARGB($black);
                }

                // ======= Controller/HQ cells: blue bg + white bold =======
                for ($r = $firstDataRow; $r <= $dataEndRow; $r++) {
                    $aVal = (string)$sheet->getCell("A{$r}")->getValue();
                    $bVal = (string)$sheet->getCell("B{$r}")->getValue();

                    if ($aVal !== '') {
                        $sheet->getStyle("A{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blueDark]],
                            'font' => ['bold' => true, 'color' => ['argb' => $white]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                    }
                    if ($bVal !== '') {
                        $sheet->getStyle("B{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blueDark]],
                            'font' => ['bold' => true, 'color' => ['argb' => $white]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                    }

                    // Type column: blue bg + white
                    $sheet->getStyle("C{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blueDark]],
                        'font' => ['bold' => true, 'color' => ['argb' => $white]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // ======= Merge controller cells vertically =======
                $r = $firstDataRow;
                while ($r <= $dataEndRow) {
                    $aVal = (string)$sheet->getCell("A{$r}")->getValue();
                    if ($aVal !== '') {
                        $mergeEnd = $r;
                        for ($rr = $r + 1; $rr <= $dataEndRow; $rr++) {
                            if ((string)$sheet->getCell("A{$rr}")->getValue() !== '') break;
                            $mergeEnd = $rr;
                        }
                        if ($mergeEnd > $r) {
                            $sheet->mergeCells("A{$r}:A{$mergeEnd}");
                        }
                        $r = $mergeEnd + 1;
                    } else {
                        $r++;
                    }
                }

                // ======= Merge HQ cells vertically (groups of 3) =======
                $r = $firstDataRow;
                while ($r <= $dataEndRow) {
                    $bVal = (string)$sheet->getCell("B{$r}")->getValue();
                    if ($bVal !== '') {
                        $mergeEnd = min($r + 2, $dataEndRow);
                        if ($mergeEnd > $r) {
                            $sheet->mergeCells("B{$r}:B{$mergeEnd}");
                        }
                        $r = $mergeEnd + 1;
                    } else {
                        $r++;
                    }
                }

                // ======= Formatos numéricos =======
                $sheet->getStyle("{$firstDayCol}{$firstDataRow}:{$endCol}{$footerExcelRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.##');
                $sheet->getStyle("{$endCol}{$firstDataRow}:{$endCol}{$footerExcelRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // ======= Alineación: datos centrados =======
                $sheet->getStyle("{$firstDayCol}{$firstDataRow}:{$endCol}{$footerExcelRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ======= Anchos =======
                $sheet->getColumnDimension('A')->setWidth(9);
                $sheet->getColumnDimension('B')->setWidth(10);
                $sheet->getColumnDimension('C')->setWidth(6);
                for ($i = 4; $i <= $lastColIdx - 1; $i++) {
                    $d = $i - 3; // día del mes
                    $isSunday = $monthStart->day($d)->isSunday();
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))
                        ->setWidth($isSunday ? 3.0 : 5.0);
                }
                $sheet->getColumnDimension($endCol)->setWidth(9);

                // ======= Footer: TOTAL GENERAL =======
                $sheet->getStyle("A{$footerExcelRow}:{$endCol}{$footerExcelRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                    'font' => ['bold' => true, 'color' => ['argb' => $black]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => $black]],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => $black]],
                        'left'   => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                        'right'  => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                        'vertical' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                // Footer values should be black (override teal from data range)
                $sheet->getStyle("{$firstDayCol}{$footerExcelRow}:{$endCol}{$footerExcelRow}")
                    ->getFont()->getColor()->setARGB($black);

                // ======= Ocultar columnas extra =======
                for ($c = $lastColIdx + 1; $c <= $lastColIdx + 20; $c++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setVisible(false);
                }
            },
        ];
    }



    /* ================== BUILD ================== */

    protected function build(): void
    {
        if (!Schema::hasTable($this->paymentsTable)) {
            $this->rows   = [];
            $this->footer = [];
            return;
        }

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end   = $start->endOfMonth();

        // ALL controllers with their assigned HQs
        $controllers = User::role('controlador')
            ->with('headquarters:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Aggregation by user/hq/type/day
        $aggs = DB::table($this->paymentsTable . ' as p')
            ->selectRaw("
                p.user_id,
                p.headquarter_id,
                DAY(p.date_register) as d,
                UPPER(p.type) as t,
                SUM(p.{$this->amountCol}) as s
            ")
            ->whereNotNull('p.date_register')
            ->whereBetween('p.date_register', [$start->toDateString(), $end->toDateString()])
            ->whereIn(DB::raw('UPPER(p.type)'), ['PAGO','RETRASO','DEUDA'])
            ->groupBy('p.user_id', 'p.headquarter_id', 'd', 't')
            ->get();

        // Index: [user_id][hq_id][TYPE][day] = sum
        $matrix = [];
        foreach ($aggs as $r) {
            $uid = (int)($r->user_id ?? 0);
            $hq  = (int)($r->headquarter_id ?? 0);
            $d   = (int)$r->d;
            $t   = (string)$r->t;
            $s   = (float)$r->s;
            $matrix[$uid][$hq][$t][$d] = ($matrix[$uid][$hq][$t][$d] ?? 0.0) + $s;
        }

        $totalsPerDay = array_fill(1, $this->daysInMonth, 0.0);
        $rows = [];

        foreach ($controllers as $u) {
            $uid   = (int)$u->id;
            $uname = (string)$u->name;
            $hqs   = $u->headquarters->sortBy('name');

            if ($hqs->isEmpty()) {
                $hqs = collect([(object)['id' => 0, 'name' => '-']]);
            }

            $firstHq = true;
            foreach ($hqs as $hq) {
                $hqId   = (int)$hq->id;
                $hqName = (string)$hq->name;

                foreach (['PAGO','RETRASO','DEUDA'] as $type) {
                    $row = [
                        $firstHq && $type === 'PAGO' ? $uname : '',
                        $type === 'PAGO' ? $hqName : '',
                        ucfirst(strtolower($type)),
                    ];

                    $sumRow = 0.0;
                    for ($d = 1; $d <= $this->daysInMonth; $d++) {
                        $val = (float)($matrix[$uid][$hqId][$type][$d] ?? 0.0);
                        $row[] = $val > 0 ? $val : '';
                        $sumRow += $val;
                        $totalsPerDay[$d] += $val;
                    }
                    $row[] = $sumRow > 0 ? $sumRow : '';

                    $rows[] = $row;
                }
                $firstHq = false;
            }
        }

        // Footer: TOTAL GENERAL
        $grand = array_sum($totalsPerDay);
        $footer = ['', 'TOTAL GENERAL', ''];
        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $footer[] = $totalsPerDay[$d] > 0 ? $totalsPerDay[$d] : '';
        }
        $footer[] = $grand > 0 ? $grand : '';

        $this->rows   = $rows;
        $this->footer = $footer;
    }

    /* ================== HELPERS ================== */

    protected function mesTexto(int $m): string
    {
        return [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ][$m] ?? (string)$m;
    }
}
