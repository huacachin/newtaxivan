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

                $row = 1;
                $lastMainCol = 'P'; // A..P

                // Colores
                $blueHeader  = 'FF2874A6'; // encabezados
                $red         = 'F80000'; // diferencias/ceros

                // Estilos helper
                $thin = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$red]]]];
                $center = ['alignment'=>[
                    'horizontal'=>Alignment::HORIZONTAL_CENTER,
                    'vertical'=>Alignment::VERTICAL_CENTER
                ]];

                // Helper: fondo índigo, texto blanco y negrita (centrado)
                $paintIndigo = function(string $range) use ($s, $center, $blueHeader) {
                    $s->getStyle($range)->applyFromArray($center)
                        ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $s->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                };

                /* ===================== CUADRO 1 ===================== */
                // Título (fondo rojo, tamaño 10)
                $s->mergeCells("A{$row}:{$lastMainCol}{$row}");
                $s->setCellValue("A{$row}", "REPORTE ESTADISTICO DE SALIDAS-PAGOS DE CONTROLADOR {$this->year}");
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('D32F2F');
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
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
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                $rowHeader = $row;
                $row++;

                $startTable = $row;

                // Cuerpo con A merged vertical por controlador
                foreach ($this->rows as $block) {
                    $blockStart = $row;

                    // Paraderos
                    foreach ($block['paraderos'] as $p) {
                        // B: HQ name  |  C: "Ingr. Sal."  -> fondo indigo
                        $s->setCellValue("B{$row}", $p['sucursal']);
                        $s->setCellValue("C{$row}", 'Ingr. Sal.');
                        $paintIndigo("B{$row}");
                        $paintIndigo("C{$row}");

                        // D..O valores de cada mes
                        for ($m=1; $m<=12; $m++) {
                            $s->setCellValueByColumnAndRow(3+$m, $row, (float)($p['ingresos_mes'][$m] ?? 0));
                        }
                        $s->setCellValue("P{$row}", (float)$p['total']);
                        $row++;
                    }

                    // Egreso Pago
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Egreso Pago');
                    $paintIndigo("B{$row}:C{$row}");
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['egreso_pago'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_egr_pago']);
                    $s->getStyle("D{$row}:P{$row}")->getFont()->getColor()->setRGB($red);
                    $row++;

                    // Egreso Draco
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Egreso Draco');
                    $paintIndigo("B{$row}:C{$row}");
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['egreso_draco'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_egr_draco']);
                    $s->getStyle("D{$row}:P{$row}")->getFont()->getColor()->setRGB($red);
                    $row++;

                    // Saldo
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Saldo');
                    $paintIndigo("B{$row}:C{$row}");
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['saldos'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_saldo']);
                    $row++;

                    // Merge columna A (CONTROL.)
                    $blockEnd = $row - 1;
                    $s->mergeCells("A{$blockStart}:A{$blockEnd}");
                    $s->setCellValue("A{$blockStart}", $block['controlador']);
                    $paintIndigo("A{$blockStart}:A{$blockEnd}");
                }

                // SALDO A FAVOR (A:C merge con fondo celeste)
                $s->mergeCells("A{$row}:C{$row}");
                $s->setCellValue("A{$row}", 'SALDO A FAVOR');
                for ($m=1; $m<=12; $m++) {
                    $s->setCellValueByColumnAndRow(3+$m, $row, (float)($this->totalesSaldoMes[$m] ?? 0));
                }
                $s->setCellValue("P{$row}", (float)$this->totalSaldoFavor);
                $s->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                $endTable = $row;
                $row += 2;

                // Bordes y centrado del cuadro 1
                $s->getStyle("A{$rowHeader}:P{$endTable}")->applyFromArray($thin);
                $s->getStyle("A{$startTable}:P{$endTable}")->applyFromArray($center);

                // Ceros en rojo (cuadro 1)
                for ($r=$startTable; $r<=$endTable; $r++) {
                    for ($c=4; $c<=16; $c++) {
                        $coord = Coordinate::stringFromColumnIndex($c).$r;
                        $val = $s->getCell($coord)->getValue();
                        if ($val === null || $val === '') { $s->setCellValue($coord, 0); $val = 0; }
                        if ((float)$val == 0.0) {
                            $s->getStyle($coord)->getFont()->getColor()->setRGB($red);
                        }
                    }
                }

                /* ===================== CUADRO 2 – COMPARATIVO ===================== */
                // Título (azul, tamaño 10)
                $s->mergeCells("A{$row}:P{$row}");
                $s->setCellValue("A{$row}", 'REPORTE COMPARATIVO');
                $s->getStyle("A{$row}:P{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $s->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                $row++;

                // ENCABEZADO 4 filas con mismo color
                $hdr1 = $row;              // fila 1
                $hdr2 = $hdr1 + 1;         // sedes
                $hdr3 = $hdr1 + 2;         // Ingreso
                $hdr4 = $hdr1 + 3;         // Salida

                // Fila 1
                $s->mergeCells("A{$hdr1}:A{$hdr4}"); $s->setCellValue("A{$hdr1}", 'Item');
                $s->mergeCells("B{$hdr1}:B{$hdr4}"); $s->setCellValue("B{$hdr1}", 'Mes');

                $s->mergeCells("C{$hdr1}:D{$hdr1}"); $s->setCellValue("C{$hdr1}", 'Luis');      // Huaycan
                $s->mergeCells("E{$hdr1}:F{$hdr1}"); $s->setCellValue("E{$hdr1}", 'Elmer');     // Huaycan
                $s->mergeCells("G{$hdr1}:H{$hdr4}"); $s->setCellValue("G{$hdr1}", 'Diferencia');// Dif Huaycan

                $s->mergeCells("I{$hdr1}:J{$hdr1}"); $s->setCellValue("I{$hdr1}", 'Luis');      // La Victoria/Gamarra
                $s->mergeCells("K{$hdr1}:L{$hdr1}"); $s->setCellValue("K{$hdr1}", 'Elmer');     // La Victoria/Gamarra
                $s->mergeCells("M{$hdr1}:N{$hdr4}"); $s->setCellValue("M{$hdr1}", 'Diferencia');// Dif LV/Gamarra

                $s->mergeCells("O{$hdr1}:P{$hdr4}");
                $s->setCellValue("O{$hdr1}", "Diferencia Huaycan / Gamarra");

                foreach ([$hdr1,$hdr2,$hdr3,$hdr4] as $hr) {
                    $s->getStyle("A{$hr}:P{$hr}")
                        ->applyFromArray($center)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $s->getStyle("A{$hr}:P{$hr}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                }
                $s->getStyle("O{$hdr1}:P{$hdr1}")->getAlignment()->setWrapText(true);

                // Fila 2 – sedes
                $s->mergeCells("C{$hdr2}:D{$hdr2}"); $s->setCellValue("C{$hdr2}", '01 Huaycan');
                $s->mergeCells("E{$hdr2}:F{$hdr2}"); $s->setCellValue("E{$hdr2}", '01 Huaycan');
                $s->mergeCells("I{$hdr2}:J{$hdr2}"); $s->setCellValue("I{$hdr2}", '04 La victoria');
                $s->mergeCells("K{$hdr2}:L{$hdr2}"); $s->setCellValue("K{$hdr2}", '04 La victoria');

                // Fila 3 – Ingreso
                foreach (['C','D','E','F','I','J','K','L'] as $colLet) {
                    $s->setCellValue("{$colLet}{$hdr3}", 'Ingreso');
                }
                // Fila 4 – Salida
                foreach (['C','D','E','F','I','J','K','L'] as $colLet) {
                    $s->setCellValue("{$colLet}{$hdr4}", 'Salida');
                }

                // Datos
                $row = $hdr4 + 1;
                $startComp = $row;

                foreach ($this->comparativo as $r) {
                    $s->setCellValue("A{$row}", $r['item']);
                    $s->setCellValue("B{$row}", $r['mes']);

                    $s->mergeCells("C{$row}:D{$row}"); $s->setCellValue("C{$row}", (float)$r['a_h']);
                    $s->mergeCells("E{$row}:F{$row}"); $s->setCellValue("E{$row}", (float)$r['b_h']);
                    $s->mergeCells("G{$row}:H{$row}"); $s->setCellValue("G{$row}", (float)$r['dif_h']);

                    $s->mergeCells("I{$row}:J{$row}"); $s->setCellValue("I{$row}", (float)$r['a_v']);
                    $s->mergeCells("K{$row}:L{$row}"); $s->setCellValue("K{$row}", (float)$r['b_v']);
                    $s->mergeCells("M{$row}:N{$row}"); $s->setCellValue("M{$row}", (float)$r['dif_v']);

                    $s->mergeCells("O{$row}:P{$row}"); $s->setCellValue("O{$row}", (float)$r['dif_h_vs_v']);
                    $row++;
                }

                // Totales
                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", 'Total');
                $s->mergeCells("C{$row}:D{$row}"); $s->setCellValue("C{$row}", (float)$this->comparativoTotales['a_h']);
                $s->mergeCells("E{$row}:F{$row}"); $s->setCellValue("E{$row}", (float)$this->comparativoTotales['b_h']);
                $s->mergeCells("G{$row}:H{$row}"); $s->setCellValue("G{$row}", (float)$this->comparativoTotales['dif_h']);
                $s->mergeCells("I{$row}:J{$row}"); $s->setCellValue("I{$row}", (float)$this->comparativoTotales['a_v']);
                $s->mergeCells("K{$row}:L{$row}"); $s->setCellValue("K{$row}", (float)$this->comparativoTotales['b_v']);
                $s->mergeCells("M{$row}:N{$row}"); $s->setCellValue("M{$row}", (float)$this->comparativoTotales['dif_v']);
                $s->mergeCells("O{$row}:P{$row}"); $s->setCellValue("O{$row}", (float)$this->comparativoTotales['dif_h_vs_v']);
                $s->getStyle("A{$row}:P{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true)->setSize(10);
                $s->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                $endComp = $row;

                // Bordes / centrado / diferencias en rojo
                $s->getStyle("A".($startComp-5).":P{$endComp}")->applyFromArray($thin);
                $s->getStyle("A{$startComp}:P{$endComp}")->applyFromArray($center);
                $s->getStyle("G{$startComp}:H{$endComp}")->getFont()->getColor()->setRGB($red);
                $s->getStyle("M{$startComp}:N{$endComp}")->getFont()->getColor()->setRGB($red);

                /* ===================== ANCHOS “AL RAS” ===================== */
                foreach (range('A','P') as $colLet) {
                    $s->getColumnDimension($colLet)->setAutoSize(false);
                }
                // Cuadro principal
                $s->getColumnDimension('A')->setWidth(9);   // CONTROL.
                $s->getColumnDimension('B')->setWidth(21);  // PARADERO
                $s->getColumnDimension('C')->setWidth(10);  // "Ingr. Sal." / etiquetas
                foreach (range('D','O') as $c) { $s->getColumnDimension($c)->setWidth(9); } // meses
                $s->getColumnDimension('P')->setWidth(11);  // TOTAL

                // Formatos numéricos (2 decimales) en D..P del cuadro principal y todo el comparativo
                // Principal (filas de datos, desde $startTable hasta $endTable, columnas D..P)
                for ($r=$startTable; $r<=$endTable; $r++) {
                    for ($c=4; $c<=16; $c++) {
                        $coord = Coordinate::stringFromColumnIndex($c).$r;
                        $s->getStyle($coord)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    }
                }
                // Comparativo (desde $startComp hasta $endComp, columnas C..P)
                for ($r=$startComp; $r<=$endComp; $r++) {
                    foreach (range('C','P') as $colLet) {
                        $s->getStyle("{$colLet}{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    }
                }
            },
        ];
    }


}
