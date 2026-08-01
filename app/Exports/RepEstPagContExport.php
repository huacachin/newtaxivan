<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class RepEstPagContExport implements WithEvents, WithColumnFormatting, FromArray, Responsable, WithTitle
{
    use Exportable;

    public string $fileName = 'reporte_salidas_pagos_controlador.xlsx';

    private array $rows;
    private array $totalesSaldoMes;
    private float $totalSaldoFavor;
    private array $comparativo;
    private array $comparativoTotales;
    private int $year;

    // 16 columnas: A..P
    private array $monthLabels = [
        1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
        7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE',
    ];

    public function __construct(
        array $rows,
        array $totalesSaldoMes,
        float $totalSaldoFavor,
        array $comparativo,
        array $comparativoTotales,
        int $year
    ) {
        $this->rows               = $rows;
        $this->totalesSaldoMes    = $totalesSaldoMes;
        $this->totalSaldoFavor    = $totalSaldoFavor;
        $this->comparativo        = $comparativo;
        $this->comparativoTotales = $comparativoTotales;
        $this->year               = $year;
    }

    /** Forzamos creación de hoja para que se dispare AfterSheet */
    public function array(): array
    {
        return [['']]; // dummy
    }

    public function columnFormats(): array
    {
        $formats = [];
        for ($i = 4; $i <= 16; $i++) {
            $formats[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_NUMBER_00;
        }
        $formats['C'] = NumberFormat::FORMAT_NUMBER_00;
        return $formats;
    }

    public function title(): string
    {
        return 'Rep Salidas y Pagos';
    }


    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {

                $s = $e->sheet->getDelegate();
                // Fuente global: Calibri 10 + sin grillas
                $s->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
                $s->setShowGridlines(false);
                $s->getDefaultRowDimension()->setRowHeight(15);

                $row = 1;
                $lastMainCol = 'P'; // A..P

                // Colores
                $blue    = '2874A6';
                $celeste = 'CEE7FF';
                $red     = 'F80000';
                $white   = 'FFFFFF';
                $black   = '000000';
                $gray    = '808080';

                // Helper: fondo azul, texto blanco, negrita, centrado
                $paintBlue = function (string $range) use ($s, $blue, $white) {
                    $s->getStyle($range)->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]],
                        'font'      => ['bold' => true, 'color' => ['rgb' => $white]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                };

                /* ===================== CUADRO 1 ===================== */
                // Título
                $s->mergeCells("A{$row}:{$lastMainCol}{$row}");
                $s->setCellValue("A{$row}", "REPORTE ESTADISTICO DE SALIDAS-PAGOS DE CONTROLADOR {$this->year}");
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]]],
                ]);
                $s->getRowDimension($row)->setRowHeight(20);
                $row++;

                // Encabezado: CONTROL., PARADERO (B:C), meses (D..O), TOTAL (P)
                $s->setCellValue("A{$row}", 'CONTROL.');
                $s->mergeCells("B{$row}:C{$row}");
                $s->setCellValue("B{$row}", 'PARADERO');
                $col = 4; // D
                foreach ($this->monthLabels as $lab) {
                    $s->setCellValueByColumnAndRow($col, $row, $lab);
                    $col++;
                }
                $s->setCellValue("P{$row}", 'TOTAL');
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]],
                    'font'      => ['bold' => true, 'color' => ['rgb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $white]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]],
                    ],
                ]);
                $s->getRowDimension($row)->setRowHeight(17);
                $rowHeader = $row;
                $row++;

                $startTable = $row;

                // Cuerpo con A merged vertical por controlador
                foreach ($this->rows as $block) {
                    $blockStart = $row;

                    // Paraderos
                    foreach ($block['paraderos'] as $p) {
                        $s->setCellValue("B{$row}", $p['sucursal']);
                        $s->setCellValue("C{$row}", 'Ingr. Sal.');
                        $paintBlue("B{$row}");
                        $paintBlue("C{$row}");

                        for ($m=1; $m<=12; $m++) {
                            $val = (float)($p['ingresos_mes'][$m] ?? 0);
                            $s->setCellValueByColumnAndRow(3+$m, $row, $val);
                            if ($val < 0) {
                                $col = Coordinate::stringFromColumnIndex(3+$m);
                                $s->getStyle("{$col}{$row}")->applyFromArray([
                                    'font' => ['bold' => true, 'color' => ['rgb' => $red]],
                                ]);
                            }
                        }
                        $totalP = (float)$p['total'];
                        $s->setCellValue("P{$row}", $totalP);
                        if ($totalP < 0) {
                            $s->getStyle("P{$row}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => $red]],
                            ]);
                        }
                        $row++;
                    }

                    // Egreso Pago
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Egreso Pago');
                    $paintBlue("B{$row}:C{$row}");
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['egreso_pago'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_egr_pago']);
                    $s->getStyle("D{$row}:P{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $red]],
                    ]);
                    $row++;

                    // Egreso Draco
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Egreso Draco');
                    $paintBlue("B{$row}:C{$row}");
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['egreso_draco'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_egr_draco']);
                    $s->getStyle("D{$row}:P{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $red]],
                    ]);
                    $row++;

                    // Saldo
                    $saldoRow = $row;
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Saldo');
                    $paintBlue("B{$row}:C{$row}");
                    for ($m=1; $m<=12; $m++) {
                        $val = (float)($block['saldos'][$m] ?? 0);
                        $s->setCellValueByColumnAndRow(3+$m, $row, $val);
                        $col = Coordinate::stringFromColumnIndex(3+$m);
                        $s->getStyle("{$col}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => $val < 0 ? $red : $black]],
                        ]);
                    }
                    $totSaldo = (float)$block['tot_saldo'];
                    $s->setCellValue("P{$row}", $totSaldo);
                    $s->getStyle("P{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $totSaldo < 0 ? $red : $black]],
                    ]);
                    $row++;

                    // Merge columna A (CONTROL.)
                    $blockEnd = $row - 1;
                    $s->mergeCells("A{$blockStart}:A{$blockEnd}");
                    $s->setCellValue("A{$blockStart}", $block['controlador']);
                    $paintBlue("A{$blockStart}:A{$blockEnd}");
                }

                // SALDO A FAVOR (footer — celeste)
                $s->mergeCells("A{$row}:C{$row}");
                $s->setCellValue("A{$row}", 'SALDO A FAVOR');
                for ($m=1; $m<=12; $m++) {
                    $s->setCellValueByColumnAndRow(3+$m, $row, (float)($this->totalesSaldoMes[$m] ?? 0));
                }
                $s->setCellValue("P{$row}", (float)$this->totalSaldoFavor);
                $s->getStyle("A{$row}:P{$row}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $celeste]],
                    'font'      => ['bold' => true, 'color' => ['rgb' => $black]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]]],
                ]);
                $s->getRowDimension($row)->setRowHeight(18);
                $endTable = $row;
                $row += 2;

                // Bordes datos cuadro 1: punteado horizontal + sólido vertical
                if ($startTable <= $endTable - 1) {
                    $s->getStyle("A{$startTable}:P" . ($endTable - 1))->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }
                // Centrado cuadro 1
                $s->getStyle("A{$startTable}:P{$endTable}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Re-outline encabezado cuadro 1
                $s->getStyle("A{$rowHeader}:P{$rowHeader}")->applyFromArray([
                    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]]],
                ]);

                // Rellenar celdas vacías con 0 (cuadro 1)
                for ($r=$startTable; $r<=$endTable; $r++) {
                    for ($c=4; $c<=16; $c++) {
                        $coord = Coordinate::stringFromColumnIndex($c).$r;
                        $val = $s->getCell($coord)->getValue();
                        if ($val === null || $val === '') { $s->setCellValue($coord, 0); }
                    }
                }

                /* ===================== CUADRO 2 – COMPARATIVO ===================== */
                // Título
                $s->mergeCells("A{$row}:P{$row}");
                $s->setCellValue("A{$row}", 'REPORTE COMPARATIVO');
                $s->getStyle("A{$row}:P{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]]],
                ]);
                $s->getRowDimension($row)->setRowHeight(20);
                $row++;

                // ENCABEZADO 4 filas
                $hdr1 = $row;
                $hdr2 = $hdr1 + 1;
                $hdr3 = $hdr1 + 2;
                $hdr4 = $hdr1 + 3;

                $s->mergeCells("A{$hdr1}:A{$hdr4}"); $s->setCellValue("A{$hdr1}", 'Nº');
                $s->mergeCells("B{$hdr1}:B{$hdr4}"); $s->setCellValue("B{$hdr1}", 'Mes');

                $s->mergeCells("C{$hdr1}:D{$hdr1}"); $s->setCellValue("C{$hdr1}", 'Luis');
                $s->mergeCells("E{$hdr1}:F{$hdr1}"); $s->setCellValue("E{$hdr1}", 'Elmer');
                $s->mergeCells("G{$hdr1}:H{$hdr4}"); $s->setCellValue("G{$hdr1}", 'Diferencia');

                $s->mergeCells("I{$hdr1}:J{$hdr1}"); $s->setCellValue("I{$hdr1}", 'Luis');
                $s->mergeCells("K{$hdr1}:L{$hdr1}"); $s->setCellValue("K{$hdr1}", 'Elmer');
                $s->mergeCells("M{$hdr1}:N{$hdr4}"); $s->setCellValue("M{$hdr1}", 'Diferencia');

                $s->mergeCells("O{$hdr1}:P{$hdr4}");
                $s->setCellValue("O{$hdr1}", "Diferencia Huaycan / Gamarra");

                foreach ([$hdr1,$hdr2,$hdr3,$hdr4] as $hr) {
                    $s->getStyle("A{$hr}:P{$hr}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blue]],
                        'font'      => ['bold' => true, 'color' => ['rgb' => $white]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $white]],
                            'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]],
                        ],
                    ]);
                }
                $s->getStyle("O{$hdr1}:P{$hdr1}")->getAlignment()->setWrapText(true);

                // Fila 2 – sedes
                $s->mergeCells("C{$hdr2}:D{$hdr2}"); $s->setCellValue("C{$hdr2}", '01 Huaycan');
                $s->mergeCells("E{$hdr2}:F{$hdr2}"); $s->setCellValue("E{$hdr2}", '01 Huaycan');
                $s->mergeCells("I{$hdr2}:J{$hdr2}"); $s->setCellValue("I{$hdr2}", '04 La victoria');
                $s->mergeCells("K{$hdr2}:L{$hdr2}"); $s->setCellValue("K{$hdr2}", '04 La victoria');

                // Fila 3 – Ingreso / Fila 4 – Salida
                foreach (['C','D','E','F','I','J','K','L'] as $colLet) {
                    $s->setCellValue("{$colLet}{$hdr3}", 'Ingreso');
                    $s->setCellValue("{$colLet}{$hdr4}", 'Salida');
                }

                // Datos comparativo
                $row = $hdr4 + 1;
                $startComp = $row;

                foreach ($this->comparativo as $cRow) {
                    $s->setCellValue("A{$row}", $cRow['item']);
                    $s->setCellValue("B{$row}", $cRow['mes']);

                    $s->mergeCells("C{$row}:D{$row}"); $s->setCellValue("C{$row}", (float)$cRow['a_h']);
                    $s->mergeCells("E{$row}:F{$row}"); $s->setCellValue("E{$row}", (float)$cRow['b_h']);
                    $s->mergeCells("G{$row}:H{$row}"); $s->setCellValue("G{$row}", (float)$cRow['dif_h']);

                    $s->mergeCells("I{$row}:J{$row}"); $s->setCellValue("I{$row}", (float)$cRow['a_v']);
                    $s->mergeCells("K{$row}:L{$row}"); $s->setCellValue("K{$row}", (float)$cRow['b_v']);
                    $s->mergeCells("M{$row}:N{$row}"); $s->setCellValue("M{$row}", (float)$cRow['dif_v']);

                    $s->mergeCells("O{$row}:P{$row}"); $s->setCellValue("O{$row}", (float)$cRow['dif_h_vs_v']);
                    $row++;
                }

                // Total comparativo (footer — celeste)
                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", 'Total');
                $s->mergeCells("C{$row}:D{$row}"); $s->setCellValue("C{$row}", (float)$this->comparativoTotales['a_h']);
                $s->mergeCells("E{$row}:F{$row}"); $s->setCellValue("E{$row}", (float)$this->comparativoTotales['b_h']);
                $s->mergeCells("G{$row}:H{$row}"); $s->setCellValue("G{$row}", (float)$this->comparativoTotales['dif_h']);
                $s->mergeCells("I{$row}:J{$row}"); $s->setCellValue("I{$row}", (float)$this->comparativoTotales['a_v']);
                $s->mergeCells("K{$row}:L{$row}"); $s->setCellValue("K{$row}", (float)$this->comparativoTotales['b_v']);
                $s->mergeCells("M{$row}:N{$row}"); $s->setCellValue("M{$row}", (float)$this->comparativoTotales['dif_v']);
                $s->mergeCells("O{$row}:P{$row}"); $s->setCellValue("O{$row}", (float)$this->comparativoTotales['dif_h_vs_v']);
                $s->getStyle("A{$row}:P{$row}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $celeste]],
                    'font'      => ['bold' => true, 'color' => ['rgb' => $black]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $black]]],
                ]);
                $s->getRowDimension($row)->setRowHeight(18);
                $endComp = $row;

                // Bordes datos comparativo: punteado horizontal + sólido vertical
                if ($startComp <= $endComp - 1) {
                    $s->getStyle("A{$startComp}:P" . ($endComp - 1))->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }
                // Centrado + diferencias en rojo
                $s->getStyle("A{$startComp}:P{$endComp}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $s->getStyle("G{$startComp}:H{$endComp}")->getFont()->getColor()->setRGB($red);
                $s->getStyle("M{$startComp}:N{$endComp}")->getFont()->getColor()->setRGB($red);

                /* ===================== ANCHOS "AL RAS" ===================== */
                foreach (range('A','P') as $colLet) {
                    $s->getColumnDimension($colLet)->setAutoSize(false);
                }
                $s->getColumnDimension('A')->setWidth(9);
                $s->getColumnDimension('B')->setWidth(21);
                $s->getColumnDimension('C')->setWidth(10);
                foreach (range('D','O') as $c) { $s->getColumnDimension($c)->setWidth(9); }
                $s->getColumnDimension('P')->setWidth(11);

                // Ocultar columnas Q en adelante
                for ($c = 17; $c <= 50; $c++) {
                    $s->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setVisible(false);
                }

                // Formatos numéricos (2 decimales)
                for ($r=$startTable; $r<=$endTable; $r++) {
                    for ($c=4; $c<=16; $c++) {
                        $coord = Coordinate::stringFromColumnIndex($c).$r;
                        $s->getStyle($coord)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    }
                }
                for ($r=$startComp; $r<=$endComp; $r++) {
                    foreach (range('C','P') as $colLet) {
                        $s->getStyle("{$colLet}{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    }
                }

                // ===== Impresión: una sola hoja (vertical, sin márgenes, escala 80) =====
                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setScale(80)
                    ->setHorizontalCentered(true)
                    ->setPrintArea("A1:P{$endComp}");
                $s->getPageMargins()
                    ->setTop(0)->setRight(0)->setBottom(0)->setLeft(0)
                    ->setHeader(0)->setFooter(0);
            },
        ];
    }


}
