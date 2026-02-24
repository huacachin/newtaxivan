<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CajaEstadisticaExport implements WithEvents
{
    use Exportable;

    /* ===== Tablas / Columnas ===== */
    private const TBL_PAYMENTS  = 'payments';
    private const PAY_DATE      = 'date_register';   // <- campo correcto
    private const PAY_HQ_ID     = 'headquarter_id';
    private const PAY_TYPE      = 'type';            // PAGO | RETRASO | DEUDA
    private const PAY_AMOUNT    = 'amount';

    private const TBL_DEPARTURES = 'departures';
    private const DEP_DATE       = 'date';
    private const DEP_HQ_ID      = 'headquarter_id';
    private const DEP_IS_SUPPORT = 'is_support';     // 0 empresa / 1 apoyo
    private const DEP_PRICE      = 'price';

    private const TBL_INCOMES = 'incomes';
    private const INC_DATE    = 'date';
    private const INC_HQ_ID   = 'headquarter_id';
    private const INC_TOTAL   = 'total';

    private const TBL_EXPENSES = 'expenses';
    private const EXP_DATE     = 'date';
    private const EXP_HQ_ID    = 'headquarter_id';
    private const EXP_TOTAL    = 'total';

    /* ===== Parámetros ===== */
    private int $year;
    private int $month;
    private ?int $headquarterId;

    /* ===== Estilos / colores ===== */
    private const BLUE_DARK  = '2874A6';
    private const BLUE_LIGHT = 'CEE7FF';
    private const RED_TITLE  = 'F80000';
    private const NUM_FMT    = '#,##0.00';

    private int   $dailyStart  = 0;
    private int   $annualStart = 0;
    private array $footerRows  = [];

    public function __construct(int $year, int $month, ?int $headquarterId = null)
    {
        $this->year = $year;
        $this->month = $month;
        $this->headquarterId = $headquarterId;
    }

    /* ============================
     *  Maatwebsite: evento AfterSheet
     * ============================ */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $sheet = $e->sheet->getDelegate();

                // Fuente global Calibri 10 y sin gridlines
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
                $sheet->setShowGridlines(false);
                $sheet->getDefaultRowDimension()->setRowHeight(15);

                $row = 1;
                $lastMainCol = 'L'; // A..L (todo inicia desde A)

                // ===== Título 1 (diario) =====
                $titulo1 = 'ESTADÍSTICA DE CAJA ' . mb_strtoupper($this->monthName($this->month)) . ' DEL ' . $this->year;
                $sheet->setCellValue("A{$row}", $titulo1);
                $sheet->mergeCells("A{$row}:{$lastMainCol}{$row}");
                $sheet->getStyle("A{$row}:{$lastMainCol}{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => self::RED_TITLE], 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(20);
                $row++;

                // ===== Tabla diaria =====
                $row = $this->drawDailyTable($sheet, $row);

                // Separador
                $row += 2;

                // ===== Título 2 (anual) =====
                $titulo2 = 'ESTADÍSTICA DE CAJA ANUAL ' . $this->year;
                $sheet->setCellValue("A{$row}", $titulo2);
                $sheet->mergeCells("A{$row}:{$lastMainCol}{$row}");
                $sheet->getStyle("A{$row}:{$lastMainCol}{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => self::RED_TITLE], 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(20);
                $row++;

                // ===== Tabla anual =====
                $row = $this->drawAnnualTable($sheet, $row);

                // ===== Bordes datos: punteado horizontal + sólido vertical =====
                // Tabla diaria: datos entre fin de headers y footer
                $dailyDataStart = $this->dailyStart + 3;
                $dailyFooter    = $this->footerRows[0] ?? 0;
                if ($dailyFooter > 0 && $dailyDataStart < $dailyFooter) {
                    $sheet->getStyle("A{$dailyDataStart}:L" . ($dailyFooter - 1))->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '808080']],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '000000']],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '000000']],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '000000']],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Tabla anual: datos entre fin de headers y primer footer
                $annualDataStart = $this->annualStart + 3;
                $annualFooter    = $this->footerRows[1] ?? 0;
                if ($annualFooter > 0 && $annualDataStart < $annualFooter) {
                    $sheet->getStyle("A{$annualDataStart}:L" . ($annualFooter - 1))->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '808080']],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '000000']],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '000000']],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '000000']],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Footer rows: asegurar thin black + altura
                foreach ($this->footerRows as $fr) {
                    $sheet->getStyle("A{$fr}:L{$fr}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                    $sheet->getRowDimension($fr)->setRowHeight(18);
                }

                // Re-aplicar outline negro en headers (para que no quede tapado)
                if ($this->dailyStart > 0) {
                    $dEnd = $this->dailyStart + 2;
                    $sheet->getStyle("A{$this->dailyStart}:L{$dEnd}")->applyFromArray([
                        'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                    ]);
                }
                if ($this->annualStart > 0) {
                    $aEnd = $this->annualStart + 2;
                    $sheet->getStyle("A{$this->annualStart}:L{$aEnd}")->applyFromArray([
                        'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                    ]);
                }

                // ===== Anchos compactos (al ras) =====
                $widths = [
                    'A'=>11,
                    'B'=>13, 'C'=>13, 'D'=>13, 'E'=>13,
                    'F'=>13, 'G'=>13, 'H'=>13,
                    'I'=>13,
                    'J'=>13,
                    'K'=>13,
                    'L'=>13,
                ];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                // ===== Ocultar columnas M en adelante =====
                for ($c = 13; $c <= 50; $c++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($colLetter)->setVisible(false);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Caja Estadística';
    }


    /* ============================
     *  Bloque 1: tabla mensual (diaria)
     * ============================ */
    private function drawDailyTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow): int
    {
        $r = $startRow;
        $this->dailyStart = $startRow;

        // -------- Encabezados multinivel (todo desde A) --------
        // Fila 1
        $sheet->setCellValue("A{$r}", 'Fecha');
        $sheet->mergeCells("A{$r}:A".($r+2));
        $sheet->setCellValue("B{$r}", 'Ingreso');
        $sheet->mergeCells("B{$r}:J{$r}"); // B..J (Pago+Salida+Otros+Total)
        $sheet->setCellValue("K{$r}", 'Egreso');
        $sheet->mergeCells("K{$r}:K".($r+2));
        $sheet->setCellValue("L{$r}", 'Utilidad');
        $sheet->mergeCells("L{$r}:L".($r+2));
        $this->paintHeader($sheet, "A{$r}:L{$r}");

        // Fila 2
        $r2 = $r + 1;
        $sheet->setCellValue("B{$r2}", 'Pago');
        $sheet->mergeCells("B{$r2}:E{$r2}");   // B..E
        $sheet->setCellValue("F{$r2}", 'Salida');
        $sheet->mergeCells("F{$r2}:H{$r2}");   // F..H
        $sheet->setCellValue("I{$r2}", 'Otros');
        $sheet->mergeCells("I{$r2}:I{$r2}");
        $sheet->setCellValue("J{$r2}", 'Total');
        $sheet->mergeCells("J{$r2}:J{$r2}");
        $this->paintHeader($sheet, "B{$r2}:J{$r2}");

        // Fila 3
        $r3 = $r + 2;
        $sheet->fromArray(['Cotización','Retraso','Deuda','Total','Empresa','Apoyo','Total'], null, "B{$r3}");
        $this->paintHeader($sheet, "B{$r3}:H{$r3}");
        $this->paintHeader($sheet, "I{$r3}:I{$r3}");
        $this->paintHeader($sheet, "J{$r3}:J{$r3}");
        $this->headerBaseStyle($sheet, "A{$r}:L{$r3}");

        // -------- Datos --------
        $data  = $this->buildDailyData($this->year, $this->month, $this->headquarterId);
        $row   = $r3 + 1;
        $numFmt = self::NUM_FMT;

        foreach ($data['rows'] as $d) {
            // Fecha (texto d/m/y)
            $sheet->setCellValue("A{$row}", $d['fecha']);

            // Domingo: fondo rojo sólo en la fecha (A) y texto blanco/bold
            if (!empty($d['is_sunday'])) {
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::RED_TITLE]],
                    'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
                ]);
            }

            // Pago
            $this->num($sheet, "B{$row}", $d['cotizacion'],    $numFmt);
            $this->num($sheet, "C{$row}", $d['retraso'],       $numFmt);
            $this->num($sheet, "D{$row}", $d['deuda'],         $numFmt);
            $this->num($sheet, "E{$row}", $d['pago_total'],    $numFmt);

            // Salida
            $this->num($sheet, "F{$row}", $d['empresa'],       $numFmt);
            $this->num($sheet, "G{$row}", $d['apoyo'],         $numFmt);
            $this->num($sheet, "H{$row}", $d['salidas_total'], $numFmt);

            // Otros / Total ingreso / Egreso / Utilidad
            $this->num($sheet, "I{$row}", $d['otros'],         $numFmt);
            $this->num($sheet, "J{$row}", $d['ingresos_total'],$numFmt);
            $this->num($sheet, "K{$row}", $d['egreso'],        $numFmt);
            $this->num($sheet, "L{$row}", $d['utilidad'],      $numFmt);
            $row++;
        }

        // Fila Total
        $this->footerRows[] = $row;
        $this->paintLightTotal($sheet, "A{$row}:L{$row}");
        $sheet->setCellValue("A{$row}", 'Total');
        $t = $data['totales'];
        $this->num($sheet, "B{$row}", $t['pago'],           $numFmt);
        $this->num($sheet, "C{$row}", $t['retraso'],        $numFmt);
        $this->num($sheet, "D{$row}", $t['deuda'],          $numFmt);
        $this->num($sheet, "E{$row}", $t['pago_total'],     $numFmt);
        $this->num($sheet, "F{$row}", $t['empresa'],        $numFmt);
        $this->num($sheet, "G{$row}", $t['apoyo'],          $numFmt);
        $this->num($sheet, "H{$row}", $t['salidas_total'],  $numFmt);
        $this->num($sheet, "I{$row}", $t['otros'],          $numFmt);
        $this->num($sheet, "J{$row}", $t['ingresos_total'], $numFmt);
        $this->num($sheet, "K{$row}", $t['egreso'],         $numFmt);
        $this->num($sheet, "L{$row}", $t['utilidad'],       $numFmt);

        // Resalta columna Utilidad completa (datos + total)
        $firstDataRow = $r3 + 1;
        $this->paintUtilidadRed($sheet, $firstDataRow, $row);

        return $row;
    }


    /* ============================
     *  Bloque 2: tabla anual (mensual)
     * ============================ */
    private function drawAnnualTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow): int
    {
        $r = $startRow;
        $this->annualStart = $startRow;

        // -------- Encabezados multinivel --------
        // Fila 1
        $sheet->setCellValue("A{$r}", 'Mes');
        $sheet->mergeCells("A{$r}:A".($r+2));
        $sheet->setCellValue("B{$r}", 'Ingreso');
        $sheet->mergeCells("B{$r}:J{$r}");
        $sheet->setCellValue("K{$r}", 'Egreso');
        $sheet->mergeCells("K{$r}:K".($r+2));
        $sheet->setCellValue("L{$r}", 'Utilidad');
        $sheet->mergeCells("L{$r}:L".($r+2));
        $this->paintHeader($sheet, "A{$r}:L{$r}");

        // Fila 2
        $r2 = $r + 1;
        $sheet->setCellValue("B{$r2}", 'Pago');
        $sheet->mergeCells("B{$r2}:E{$r2}");
        $sheet->setCellValue("F{$r2}", 'Salida');
        $sheet->mergeCells("F{$r2}:H{$r2}");
        $sheet->setCellValue("I{$r2}", 'Otros');
        $sheet->mergeCells("I{$r2}:I{$r2}");
        $sheet->setCellValue("J{$r2}", 'Total');
        $sheet->mergeCells("J{$r2}:J{$r2}");
        $this->paintHeader($sheet, "B{$r2}:J{$r2}");

        // Fila 3
        $r3 = $r + 2;
        $sheet->fromArray(['Cotización', 'Retraso', 'Deuda', 'Total', 'Empresa', 'Apoyo', 'Total'], null, "B{$r3}");
        $this->paintHeader($sheet, "B{$r3}:H{$r3}");
        $this->paintHeader($sheet, "I{$r3}:I{$r3}");
        $this->paintHeader($sheet, "J{$r3}:J{$r3}");
        $this->headerBaseStyle($sheet, "A{$r}:L{$r3}");

        // -------- Datos --------
        [$rows, $totales, $promedios] = $this->buildAnnualData($this->year, $this->month, $this->headquarterId);

        $row = $r3 + 1;
        $numFmt = self::NUM_FMT;

        foreach ($rows as $d) {
            $sheet->setCellValueExplicit("A{$row}", $d['mes'], DataType::TYPE_STRING);
            $this->num($sheet, "B{$row}", $d['pago'],           $numFmt);
            $this->num($sheet, "C{$row}", $d['retraso'],        $numFmt);
            $this->num($sheet, "D{$row}", $d['deuda'],          $numFmt);
            $this->num($sheet, "E{$row}", $d['pago_total'],     $numFmt);
            $this->num($sheet, "F{$row}", $d['empresa'],        $numFmt);
            $this->num($sheet, "G{$row}", $d['apoyo'],          $numFmt);
            $this->num($sheet, "H{$row}", $d['salidas_total'],  $numFmt);
            $this->num($sheet, "I{$row}", $d['otros'],          $numFmt);
            $this->num($sheet, "J{$row}", $d['ingresos_total'], $numFmt);
            $this->num($sheet, "K{$row}", $d['egreso'],         $numFmt);
            $this->num($sheet, "L{$row}", $d['utilidad'],       $numFmt);
            $row++;
        }

        // Totales
        $this->footerRows[] = $row;
        $this->paintLightTotal($sheet, "A{$row}:L{$row}");
        $sheet->setCellValue("A{$row}", 'Total');
        $this->num($sheet, "B{$row}", $totales['pago'],           $numFmt);
        $this->num($sheet, "C{$row}", $totales['retraso'],        $numFmt);
        $this->num($sheet, "D{$row}", $totales['deuda'],          $numFmt);
        $this->num($sheet, "E{$row}", $totales['pago_total'],     $numFmt);
        $this->num($sheet, "F{$row}", $totales['empresa'],        $numFmt);
        $this->num($sheet, "G{$row}", $totales['apoyo'],          $numFmt);
        $this->num($sheet, "H{$row}", $totales['salidas_total'],  $numFmt);
        $this->num($sheet, "I{$row}", $totales['otros'],          $numFmt);
        $this->num($sheet, "J{$row}", $totales['ingresos_total'], $numFmt);
        $this->num($sheet, "K{$row}", $totales['egreso'],         $numFmt);
        $this->num($sheet, "L{$row}", $totales['utilidad'],       $numFmt);

        // Promedio
        $row++;
        $this->footerRows[] = $row;
        $this->paintLightTotal($sheet, "A{$row}:L{$row}");
        $sheet->setCellValue("A{$row}", 'Promedio');
        $this->num($sheet, "B{$row}", $promedios['pago'],           $numFmt);
        $this->num($sheet, "C{$row}", $promedios['retraso'],        $numFmt);
        $this->num($sheet, "D{$row}", $promedios['deuda'],          $numFmt);
        $this->num($sheet, "E{$row}", $promedios['pago_total'],     $numFmt);
        $this->num($sheet, "F{$row}", $promedios['empresa'],        $numFmt);
        $this->num($sheet, "G{$row}", $promedios['apoyo'],          $numFmt);
        $this->num($sheet, "H{$row}", $promedios['salidas_total'],  $numFmt);
        $this->num($sheet, "I{$row}", $promedios['otros'],          $numFmt);
        $this->num($sheet, "J{$row}", $promedios['ingresos_total'], $numFmt);
        $this->num($sheet, "K{$row}", $promedios['egreso'],         $numFmt);
        $this->num($sheet, "L{$row}", $promedios['utilidad'],       $numFmt);

        // Resalta columna Utilidad completa (datos + total + promedio)
        $firstDataRow = $r3 + 1;
        $this->paintUtilidadRed($sheet, $firstDataRow, $row);

        return $row;
    }

    /* ============================
     *  Data builders
     * ============================ */

    private function buildDailyData(int $year, int $month, ?int $hqId): array
    {
        [$start, $end] = $this->monthRange($year, $month);
        $days = Carbon::create($year, $month, 1)->endOfMonth()->day;

        // Pagos por día/tipo
        $pagos = DB::table(self::TBL_PAYMENTS)
            ->selectRaw('DATE('.self::PAY_DATE.') as d, '.self::PAY_TYPE.' as t, SUM('.self::PAY_AMOUNT.') as s')
            ->whereBetween(self::PAY_DATE, [$start, $end])
            ->when($hqId, fn($q) => $q->where(self::PAY_HQ_ID, $hqId))
            ->groupBy(DB::raw('DATE('.self::PAY_DATE.')'), self::PAY_TYPE)
            ->get()
            ->mapWithKeys(fn($r) => [ "{$r->d}|{$r->t}" => (float)$r->s ])
            ->all();

        // Salidas por día
        $salidas = DB::table(self::TBL_DEPARTURES)
            ->selectRaw('DATE('.self::DEP_DATE.') as d, '.self::DEP_IS_SUPPORT.' as spt, SUM('.self::DEP_PRICE.') as s')
            ->whereBetween(self::DEP_DATE, [$start, $end])
            ->when($hqId, fn($q) => $q->where(self::DEP_HQ_ID, $hqId))
            ->groupBy(DB::raw('DATE('.self::DEP_DATE.')'), self::DEP_IS_SUPPORT)
            ->get()
            ->mapWithKeys(fn($r) => [ "{$r->d}|{$r->spt}" => (float)$r->s ])
            ->all();

        // Otros por día
        $otros = DB::table(self::TBL_INCOMES)
            ->selectRaw('DATE('.self::INC_DATE.') as d, SUM('.self::INC_TOTAL.') as s')
            ->whereBetween(self::INC_DATE, [$start, $end])
            ->when($hqId, fn($q) => $q->where(self::INC_HQ_ID, $hqId))
            ->groupBy(DB::raw('DATE('.self::INC_DATE.')'))
            ->pluck('s','d')->all();

        // Egresos por día
        $egresos = DB::table(self::TBL_EXPENSES)
            ->selectRaw('DATE('.self::EXP_DATE.') as d, SUM('.self::EXP_TOTAL.') as s')
            ->whereBetween(self::EXP_DATE, [$start, $end])
            ->when($hqId, fn($q) => $q->where(self::EXP_HQ_ID, $hqId))
            ->groupBy(DB::raw('DATE('.self::EXP_DATE.')'))
            ->pluck('s','d')->all();

        $rows = [];
        $sum = [
            'pago'=>0,'retraso'=>0,'deuda'=>0,'pago_total'=>0,
            'empresa'=>0,'apoyo'=>0,'salidas_total'=>0,
            'otros'=>0,'ingresos_total'=>0,
            'egreso'=>0,'utilidad'=>0,
        ];

        for ($day=1; $day <= $days; $day++) {
            $dateObj = Carbon::create($year, $month, $day);
            $d   = $dateObj->toDateString();
            $lbl = $dateObj->format('d/m/y');

            $cot = (float)($pagos["{$d}|PAGO"]    ?? 0);
            $ret = (float)($pagos["{$d}|RETRASO"] ?? 0);
            $deu = (float)($pagos["{$d}|DEUDA"]   ?? 0);
            $pagTot = $cot + $ret + $deu;

            $emp = (float)($salidas["{$d}|0"] ?? 0);
            $apo = (float)($salidas["{$d}|1"] ?? 0);
            $salTot = $emp + $apo;

            $otr = (float)($otros[$d]   ?? 0);
            $egr = (float)($egresos[$d] ?? 0);

            $ingTot = $pagTot + $salTot + $otr;
            $uti    = $ingTot - $egr;

            $rows[] = [
                'fecha'         => $lbl,
                'is_sunday'     => $dateObj->isSunday(), // flag para estilo
                'cotizacion'    => $cot,
                'retraso'       => $ret,
                'deuda'         => $deu,
                'pago_total'    => $pagTot,
                'empresa'       => $emp,
                'apoyo'         => $apo,
                'salidas_total' => $salTot,
                'otros'         => $otr,
                'ingresos_total'=> $ingTot,
                'egreso'        => $egr,
                'utilidad'      => $uti,
            ];

            $sum['pago']           += $cot;
            $sum['retraso']        += $ret;
            $sum['deuda']          += $deu;
            $sum['pago_total']     += $pagTot;
            $sum['empresa']        += $emp;
            $sum['apoyo']          += $apo;
            $sum['salidas_total']  += $salTot;
            $sum['otros']          += $otr;
            $sum['ingresos_total'] += $ingTot;
            $sum['egreso']         += $egr;
            $sum['utilidad']       += $uti;
        }

        $divisor = max(1, count($rows));
        $prom = array_map(fn($v) => $v / $divisor, $sum);

        return [
            'rows'      => $rows,
            'totales'   => $sum,
            'promedios' => $prom,
        ];
    }

    private function buildAnnualData(int $year, int $selectedMonth, ?int $hqId): array
    {
        // Límite: si es año actual, hasta mes seleccionado; otros años 12 meses
        $currentYear = (int) now()->format('Y');
        $limitMonth = ($year === $currentYear) ? max(1, min(12, $selectedMonth ?: 12)) : 12;

        $rows = [];
        $sum = [
            'pago'=>0,'retraso'=>0,'deuda'=>0,'pago_total'=>0,
            'empresa'=>0,'apoyo'=>0,'salidas_total'=>0,
            'otros'=>0,'ingresos_total'=>0,
            'egreso'=>0,'utilidad'=>0,
        ];
        $monthsShown = 0;

        for ($m=1; $m<= $limitMonth; $m++) {
            [$start, $end] = $this->monthRange($year, $m);

            $pagos = DB::table(self::TBL_PAYMENTS)
                ->selectRaw(self::PAY_TYPE.' as t, SUM('.self::PAY_AMOUNT.') as s')
                ->whereBetween(self::PAY_DATE, [$start, $end])
                ->when($hqId, fn($q) => $q->where(self::PAY_HQ_ID, $hqId))
                ->groupBy(self::PAY_TYPE)
                ->pluck('s','t');

            $cot = (float)($pagos['PAGO']    ?? 0);
            $ret = (float)($pagos['RETRASO'] ?? 0);
            $deu = (float)($pagos['DEUDA']   ?? 0);
            $pagTot = $cot + $ret + $deu;

            $sal = DB::table(self::TBL_DEPARTURES)
                ->selectRaw(self::DEP_IS_SUPPORT.' as spt, SUM('.self::DEP_PRICE.') as s')
                ->whereBetween(self::DEP_DATE, [$start, $end])
                ->when($hqId, fn($q) => $q->where(self::DEP_HQ_ID, $hqId))
                ->groupBy(self::DEP_IS_SUPPORT)
                ->pluck('s','spt');

            $emp = (float)($sal[0] ?? 0);
            $apo = (float)($sal[1] ?? 0);
            $salTot = $emp + $apo;

            $otr = (float) DB::table(self::TBL_INCOMES)
                ->whereBetween(self::INC_DATE, [$start, $end])
                ->when($hqId, fn($q) => $q->where(self::INC_HQ_ID, $hqId))
                ->sum(self::INC_TOTAL);

            $egr = (float) DB::table(self::TBL_EXPENSES)
                ->whereBetween(self::EXP_DATE, [$start, $end])
                ->when($hqId, fn($q) => $q->where(self::EXP_HQ_ID, $hqId))
                ->sum(self::EXP_TOTAL);

            $ingTot = $pagTot + $salTot + $otr;
            $uti    = $ingTot - $egr;

            $row = [
                'mes'           => $this->monthName($m),
                'pago'          => $cot,
                'retraso'       => $ret,
                'deuda'         => $deu,
                'pago_total'    => $pagTot,
                'empresa'       => $emp,
                'apoyo'         => $apo,
                'salidas_total' => $salTot,
                'otros'         => $otr,
                'ingresos_total'=> $ingTot,
                'egreso'        => $egr,
                'utilidad'      => $uti,
            ];

            if ($this->rowHasMovement($row)) {
                $rows[] = $row;
                $monthsShown++;

                foreach ($row as $k => $v) {
                    if (isset($sum[$k])) {
                        $sum[$k] += (float)$v;
                    }
                }
            }
        }

        $divisor = max(1, $monthsShown);
        $prom = array_map(fn($v) => $v / $divisor, $sum);

        return [$rows, $sum, $prom];
    }

    /* ============================
     *  Helpers visuales y numéricos
     * ============================ */

    private function num(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, $value, string $fmt): void
    {
        // Si viene null → 0
        $val = ($value === null || $value === '') ? 0 : (float)$value;

        $sheet->setCellValueExplicit($cell, $val, DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($fmt);

        // Ceros en gris claro para distinguir vacíos (sin shrink de fuente)
        if ((float)$val === 0.0) {
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('444444');
        }
    }

    private function paintUtilidadRed(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $fromRow, int $toRow): void
    {
        if ($toRow >= $fromRow) {
            $sheet->getStyle("L{$fromRow}:L{$toRow}")
                ->applyFromArray(['font' => ['color' => ['rgb' => self::RED_TITLE]]]);
        }
    }

    private function paintHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::BLUE_DARK],
            ],
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);
    }

    private function headerBaseStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        // Altura de filas de cabecera (≈20pt)
        $dim = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
        for ($row = $dim[0][1]; $row <= $dim[1][1]; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    }

    private function paintLightTotal(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::BLUE_LIGHT],
            ],
            'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);
    }

    private function monthRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay()->toDateTimeString();
        $end   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay()->toDateTimeString();
        return [$start, $end];
    }

    private function monthName(int $m): string
    {
        return [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ][$m] ?? (string)$m;
    }

    private function rowHasMovement(array $row): bool
    {
        return (
            ($row['pago_total'] ?? 0) > 0 ||
            ($row['salidas_total'] ?? 0) > 0 ||
            ($row['otros'] ?? 0) > 0 ||
            ($row['egreso'] ?? 0) > 0
        );
    }
}
