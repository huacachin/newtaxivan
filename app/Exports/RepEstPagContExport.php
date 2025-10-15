<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class RepEstPagContExport implements WithEvents, WithColumnFormatting, FromArray, Responsable
{
    use Exportable;

    public string $fileName = 'reporte_salidas_pagos_controlador.xlsx';

    private array $rows;
    private array $totalesSaldoMes;
    private float $totalSaldoFavor;
    private array $comparativo;
    private array $comparativoTotales;
    private int $year;

    // 16 columnas: A..P (A=CONTROL., B:C=PARADERO, D..O=meses, P=TOTAL)
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

    /** Necesario para que se cree la hoja y se dispare AfterSheet */
    public function array(): array
    {
        return [['']]; // dummy
    }

    public function columnFormats(): array
    {
        // D..P con 2 decimales (tabla principal)
        $formats = [];
        for ($i = 4; $i <= 16; $i++) {
            $formats[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_NUMBER_00;
        }
        // En el comparativo también hay valores numéricos desde C
        $formats['C'] = NumberFormat::FORMAT_NUMBER_00;
        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {

                $s = $e->sheet->getDelegate();
                $s->getParent()->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(9);
                $s->setShowGridlines(false);

                $row = 1;
                $lastMainCol = 'P'; // 16 columnas (A..P)

                // Colores
                $blueHeader  = '2874A6'; // encabezados
                $indigo      = '1F4E79'; // barra CONTROL.
                $red         = 'C0392B'; // diferencias/ceros
                $celeste     = 'CEE7FF'; // saldo a favor / total comparativo

                // Estilos helper
                $thin = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'C9CDD1']]]];
                $center = ['alignment'=>[
                    'horizontal'=>Alignment::HORIZONTAL_CENTER,
                    'vertical'=>Alignment::VERTICAL_CENTER
                ]];

                // ===================== CUADRO 1 =====================
                // Título (rojo)
                $s->mergeCells("A{$row}:{$lastMainCol}{$row}");
                $s->setCellValue("A{$row}", "REPORTE ESTADISTICO DE SALIDAS-PAGOS DE CONTROLADOR {$this->year}");
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
                $s->getStyle("A{$row}:{$lastMainCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($red);
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

                    // Paraderos: B = nombre, C = "Ingr. Sal."
                    foreach ($block['paraderos'] as $p) {
                        $s->setCellValue("B{$row}", $p['sucursal']);
                        $s->setCellValue("C{$row}", 'Ingr. Sal.');
                        for ($m=1; $m<=12; $m++) {
                            $s->setCellValueByColumnAndRow(3+$m, $row, (float)($p['ingresos_mes'][$m] ?? 0));
                        }
                        $s->setCellValue("P{$row}", (float)$p['total']);
                        $row++;
                    }

                    // Egreso Pago (B:C merge) – números en rojo
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Egreso Pago');
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['egreso_pago'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_egr_pago']);
                    $s->getStyle("A{$row}:P{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                    $s->getStyle("D{$row}:P{$row}")->getFont()->getColor()->setRGB($red);
                    $row++;

                    // Egreso Draco
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Egreso Draco');
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['egreso_draco'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_egr_draco']);
                    $s->getStyle("A{$row}:P{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                    $s->getStyle("D{$row}:P{$row}")->getFont()->getColor()->setRGB($red);
                    $row++;

                    // Saldo
                    $s->mergeCells("B{$row}:C{$row}");
                    $s->setCellValue("B{$row}", 'Saldo');
                    for ($m=1; $m<=12; $m++) {
                        $s->setCellValueByColumnAndRow(3+$m, $row, (float)($block['saldos'][$m] ?? 0));
                    }
                    $s->setCellValue("P{$row}", (float)$block['tot_saldo']);
                    $row++;

                    // Merged de CONTROL. (columna A)
                    $blockEnd = $row - 1;
                    $s->mergeCells("A{$blockStart}:A{$blockEnd}");
                    $s->setCellValue("A{$blockStart}", $block['controlador']);
                    $s->getStyle("A{$blockStart}:A{$blockEnd}")
                        ->applyFromArray($center)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $s->getStyle("A{$blockStart}:A{$blockEnd}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($indigo);
                }

                // SALDO A FAVOR (A:C merge)
                $s->mergeCells("A{$row}:C{$row}");
                $s->setCellValue("A{$row}", 'SALDO A FAVOR');
                for ($m=1; $m<=12; $m++) {
                    $s->setCellValueByColumnAndRow(3+$m, $row, (float)($this->totalesSaldoMes[$m] ?? 0));
                }
                $s->setCellValue("P{$row}", (float)$this->totalSaldoFavor);
                $s->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($celeste);
                $endTable = $row;
                $row += 2;

                // Bordes y centrado
                $s->getStyle("A{$rowHeader}:P{$endTable}")->applyFromArray($thin);
                $s->getStyle("A{$startTable}:P{$endTable}")->applyFromArray($center);

                // Ceros en rojo (principal)
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

                // ===================== CUADRO 2 – COMPARATIVO =====================
                // Título
                $s->mergeCells("A{$row}:P{$row}");
                $s->setCellValue("A{$row}", 'REPORTE COMPARATIVO');
                $s->getStyle("A{$row}:P{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
                $s->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                $row++;

                /** ========= ENCABEZADO CON ROWSPAN 4 y MISMO COLOR EN TODAS LAS FILAS ========= **/
                $hdr1 = $row;              // fila 1 del header
                $hdr2 = $hdr1 + 1;         // fila 2 (sedes)
                $hdr3 = $hdr1 + 2;         // fila 3 (Ingreso)
                $hdr4 = $hdr1 + 3;         // fila 4 (Salida)

                // Fila 1 (cabecera principal, con merges verticales)
                $s->mergeCells("A{$hdr1}:A{$hdr4}"); $s->setCellValue("A{$hdr1}", 'Item');
                $s->mergeCells("B{$hdr1}:B{$hdr4}"); $s->setCellValue("B{$hdr1}", 'Mes');

                $s->mergeCells("C{$hdr1}:D{$hdr1}"); $s->setCellValue("C{$hdr1}", 'Luis');      // bloque Huaycan (Luis)
                $s->mergeCells("E{$hdr1}:F{$hdr1}"); $s->setCellValue("E{$hdr1}", 'Elmer');     // bloque Huaycan (Elmer)
                $s->mergeCells("G{$hdr1}:H{$hdr4}"); $s->setCellValue("G{$hdr1}", 'Diferencia');// DIF Huaycan (rowspan 4)

                $s->mergeCells("I{$hdr1}:J{$hdr1}"); $s->setCellValue("I{$hdr1}", 'Luis');      // bloque LV/Gamarra (Luis)
                $s->mergeCells("K{$hdr1}:L{$hdr1}"); $s->setCellValue("K{$hdr1}", 'Elmer');     // bloque LV/Gamarra (Elmer)
                $s->mergeCells("M{$hdr1}:N{$hdr4}"); $s->setCellValue("M{$hdr1}", 'Diferencia');// DIF LV/Gamarra (rowspan 4)

                $s->mergeCells("O{$hdr1}:P{$hdr4}");
                $s->setCellValue("O{$hdr1}", "Diferencia Huaycan / Gamarra");                  // texto exacto

                // === MISMO COLOR DE FONDO para las 4 filas del encabezado (blueHeader) ===
                foreach ([$hdr1,$hdr2,$hdr3,$hdr4] as $hr) {
                    $s->getStyle("A{$hr}:P{$hr}")
                        ->applyFromArray($center)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $s->getStyle("A{$hr}:P{$hr}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($blueHeader);
                }
                // Wrap al bloque O:P
                $s->getStyle("O{$hdr1}:P{$hdr1}")->getAlignment()->setWrapText(true);

                // Fila 2 (sedes)
                $s->mergeCells("C{$hdr2}:D{$hdr2}"); $s->setCellValue("C{$hdr2}", '01 Huaycan');
                $s->mergeCells("E{$hdr2}:F{$hdr2}"); $s->setCellValue("E{$hdr2}", '01 Huaycan');
                $s->mergeCells("I{$hdr2}:J{$hdr2}"); $s->setCellValue("I{$hdr2}", '04 La victoria');
                $s->mergeCells("K{$hdr2}:L{$hdr2}"); $s->setCellValue("K{$hdr2}", '04 La victoria');

                // Fila 3 – Ingreso (solo textos)
                foreach (['C','D','E','F','I','J','K','L'] as $colLet) {
                    $s->setCellValue("{$colLet}{$hdr3}", 'Ingreso');
                }
                // Fila 4 – Salida
                foreach (['C','D','E','F','I','J','K','L'] as $colLet) {
                    $s->setCellValue("{$colLet}{$hdr4}", 'Salida');
                }

                // Avanzamos a datos
                $row = $hdr4 + 1;
                $startComp = $row;

                // Filas de datos (12)
                foreach ($this->comparativo as $r) {
                    $s->setCellValue("A{$row}", $r['item']);
                    $s->setCellValue("B{$row}", $r['mes']);

                    $s->mergeCells("C{$row}:D{$row}"); $s->setCellValue("C{$row}", (float)$r['a_h']);   // Luis Huaycan
                    $s->mergeCells("E{$row}:F{$row}"); $s->setCellValue("E{$row}", (float)$r['b_h']);   // Elmer Huaycan
                    $s->mergeCells("G{$row}:H{$row}"); $s->setCellValue("G{$row}", (float)$r['dif_h']); // Diferencia Huaycan

                    $s->mergeCells("I{$row}:J{$row}"); $s->setCellValue("I{$row}", (float)$r['a_v']);   // Luis La victoria
                    $s->mergeCells("K{$row}:L{$row}"); $s->setCellValue("K{$row}", (float)$r['b_v']);   // Elmer La victoria
                    $s->mergeCells("M{$row}:N{$row}"); $s->setCellValue("M{$row}", (float)$r['dif_v']); // Diferencia LV

                    $s->mergeCells("O{$row}:P{$row}"); $s->setCellValue("O{$row}", (float)$r['dif_h_vs_v']); // Dif HV/Gamarra
                    $row++;
                }

                // Totales (mismo fondo que "Saldo a favor")
                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", 'Total');
                $s->mergeCells("C{$row}:D{$row}"); $s->setCellValue("C{$row}", (float)$this->comparativoTotales['a_h']);
                $s->mergeCells("E{$row}:F{$row}"); $s->setCellValue("E{$row}", (float)$this->comparativoTotales['b_h']);
                $s->mergeCells("G{$row}:H{$row}"); $s->setCellValue("G{$row}", (float)$this->comparativoTotales['dif_h']);
                $s->mergeCells("I{$row}:J{$row}"); $s->setCellValue("I{$row}", (float)$this->comparativoTotales['a_v']);
                $s->mergeCells("K{$row}:L{$row}"); $s->setCellValue("K{$row}", (float)$this->comparativoTotales['b_v']);
                $s->mergeCells("M{$row}:N{$row}"); $s->setCellValue("M{$row}", (float)$this->comparativoTotales['dif_v']);
                $s->mergeCells("O{$row}:P{$row}"); $s->setCellValue("O{$row}", (float)$this->comparativoTotales['dif_h_vs_v']);
                $s->getStyle("A{$row}:P{$row}")
                    ->applyFromArray($center)->getFont()->setBold(true);
                $s->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($celeste);
                $endComp = $row;

                // Bordes / centrado / diferencias en rojo
                $s->getStyle("A".($startComp-5).":P{$endComp}")->applyFromArray($thin);
                $s->getStyle("A{$startComp}:P{$endComp}")->applyFromArray($center);
                $s->getStyle("G{$startComp}:H{$endComp}")->getFont()->getColor()->setRGB($red);
                $s->getStyle("M{$startComp}:N{$endComp}")->getFont()->getColor()->setRGB($red);

                // ===================== ANCHOS COMPACTOS =====================
                foreach (range('A','P') as $colLet) {
                    $s->getColumnDimension($colLet)->setAutoSize(false);
                }
                // Cuadro principal
                $s->getColumnDimension('A')->setWidth(9);   // CONTROL.
                $s->getColumnDimension('B')->setWidth(21);  // PARADERO
                $s->getColumnDimension('C')->setWidth(10);  // "Ingr. Sal." / etiquetas
                foreach (range('D','O') as $c) { $s->getColumnDimension($c)->setWidth(9); } // meses
                $s->getColumnDimension('P')->setWidth(11);  // TOTAL
            },
        ];
    }
}
