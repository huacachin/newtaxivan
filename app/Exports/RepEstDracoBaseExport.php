<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class RepEstDracoBaseExport implements FromArray, ShouldAutoSize, WithHeadings, WithEvents, WithStyles
{
    public function __construct(protected int $year) {}

    /** Layout helpers (para estilos en AfterSheet) */
    private int $mainRowCount = 0;       // # filas del bloque principal (incluye base + usuarios + total general)
    private int $summaryRowStart = 0;    // fila (en el dataset, SIN considerar título/subtítulo) donde inicia Resumen por Sucursal
    private int $summaryRowEnd   = 0;    // última fila del bloque Resumen por Sucursal

    /** Meses (como en la vista) */
    private array $months = [
        1=>'ENERO', 2=>'FEBRERO', 3=>'MARZO', 4=>'ABRIL', 5=>'MAYO', 6=>'JUNIO',
        7=>'JULIO', 8=>'AGOSTO', 9=>'SEPTIEMBRE', 10=>'OCTUBRE', 11=>'NOVIEMBRE', 12=>'DICIEMBRE',
    ];

    /** ====== Construcción de datos tal como en la vista ====== */
    public function array(): array
    {
        // Mapas de nombres
        $userMap = [];
        $hqMap   = [];
        if (Schema::hasTable('users')) {
            DB::table('users')->select('id','name')->orderBy('name')->chunk(1000, function($rows) use (&$userMap){
                foreach ($rows as $r) $userMap[(int)$r->id] = (string)$r->name;
            });
        }
        if (Schema::hasTable('headquarters')) {
            DB::table('headquarters')->select('id','name')->orderBy('name')->chunk(1000, function($rows) use (&$hqMap){
                foreach ($rows as $r) $hqMap[(int)$r->id] = (string)$r->name;
            });
        }

        // BASE (Oficina): SUM(total) por mes donde reason LIKE '%BASE%'
        $baseMonthly = array_fill(1, 12, 0.0);
        $grandTotalBase = 0.0;

        if (Schema::hasTable('expenses')) {
            $base = DB::table('expenses')
                ->whereYear('date', $this->year)
                ->where('reason', 'like', '%BASE%')
                ->selectRaw('MONTH(date) m, SUM(total) s')
                ->groupBy('m')
                ->pluck('s','m');

            foreach ($base as $m => $s) {
                $i = (int)$m;
                if ($i>=1 && $i<=12) {
                    $val = (float)$s;
                    $baseMonthly[$i] = $val;
                    $grandTotalBase += $val;
                }
            }
        }

        // DRACO por usuario x sede x mes
        $groups = []; // uid => ['user' => name, 'hq_rows' => [hid => ['hq'=>, 'm'=>[1..12], 'total'=>]]]
        $totalsByMonth         = array_fill(1, 12, 0.0); // solo DRACO
        $grandTotalDraco       = 0.0;
        $totalsCombinedByMonth = array_fill(1, 12, 0.0); // DRACO + BASE
        $grandTotalCombined    = 0.0;

        if (Schema::hasTable('expenses')) {
            $rows = DB::table('expenses as e')
                ->whereYear('e.date', $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->selectRaw('e.user_id, e.headquarter_id, MONTH(e.date) m, SUM(e.total) s')
                ->groupBy('e.user_id', 'e.headquarter_id', 'm')
                ->get();

            $mkMonths = fn() => array_fill(1, 12, 0.0);

            foreach ($rows as $r) {
                $uid = (int)($r->user_id ?? 0);
                $hid = (int)($r->headquarter_id ?? 0);
                $mi  = max(1, min(12, (int)$r->m));
                $val = (float)$r->s;

                if (!isset($groups[$uid])) {
                    $groups[$uid] = ['user' => ($userMap[$uid] ?? '-'), 'hq_rows' => []];
                }
                if (!isset($groups[$uid]['hq_rows'][$hid])) {
                    $groups[$uid]['hq_rows'][$hid] = [
                        'hq'    => $hqMap[$hid] ?? ($hid ? ('HQ#'.$hid) : '-'),
                        'm'     => $mkMonths(),
                        'total' => 0.0,
                    ];
                }

                $groups[$uid]['hq_rows'][$hid]['m'][$mi] += $val;
                $groups[$uid]['hq_rows'][$hid]['total']  += $val;

                $totalsByMonth[$mi] += $val;
                $grandTotalDraco    += $val;
            }

            // Ordenar por usuario y sede (alfabético)
            uasort($groups, fn($a,$b)=>strcmp($a['user'],$b['user']));
            foreach ($groups as &$g) {
                uasort($g['hq_rows'], fn($a,$b)=>strcmp($a['hq'],$b['hq']));
            }
            unset($g);
        }

        // COMBINADO
        for ($i=1; $i<=12; $i++) {
            $totalsCombinedByMonth[$i] = ($totalsByMonth[$i] ?? 0) + ($baseMonthly[$i] ?? 0);
            $grandTotalCombined += $totalsCombinedByMonth[$i];
        }

        // Resumen por Sucursal (solo DRACO)
        $byHeadquarter = [];
        if (Schema::hasTable('expenses')) {
            $byHQ = DB::table('expenses as e')
                ->whereYear('e.date', $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->selectRaw('e.headquarter_id, SUM(e.total) s')
                ->groupBy('e.headquarter_id')
                ->get();

            foreach ($byHQ as $h) {
                $hid = (int)($h->headquarter_id ?? 0);
                $byHeadquarter[] = [
                    'hq'    => $hqMap[$hid] ?? ($hid ? ('HQ#'.$hid) : '-'),
                    'total' => (float)$h->s,
                ];
            }
            usort($byHeadquarter, fn($a,$b)=>$b['total'] <=> $a['total']);
        }

        // ====== Volcado al array (tabla PRINCIPAL como en la vista) ======
        $data = [];

        // 1) Fila BASE (OFICINA / BASE)
        $rowBase = ['OFICINA','BASE'];
        $tBase = 0.0;
        for ($m=1; $m<=12; $m++) {
            $val = (float)($baseMonthly[$m] ?? 0);
            $tBase += $val;
            $rowBase[] = $val;
        }
        $rowBase[] = $tBase;
        $data[] = $rowBase;

        // 2) Bloques por USUARIO
        if (!empty($groups)) {
            foreach ($groups as $g) {
                // Fila cabecera de usuario (azul)
                $data[] = ["__USER__:".mb_strtoupper($g['user']), '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

                // Filas por PARADERO (HQ)
                foreach ($g['hq_rows'] as $row) {
                    $line = ['', $row['hq']];
                    foreach (range(1,12) as $m) {
                        $line[] = (float)($row['m'][$m] ?? 0);
                    }
                    $line[] = (float)$row['total'];
                    $data[] = $line;
                }
            }
        } else {
            // Sin DRACO para el año
            $data[] = ['__EMPTY__','', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        // 3) TOTAL GENERAL (DRACO + BASE)
        $footer = ["__FOOTER__:TOTAL GENERAL (DRACO + BASE)", ''];
        for ($m=1; $m<=12; $m++) { $footer[] = (float)($totalsCombinedByMonth[$m] ?? 0); }
        $footer[] = (float)$grandTotalCombined;
        $data[] = $footer;

        $this->mainRowCount = count($data); // hasta aquí bloque principal

        // ====== Tabla secundaria: “Resumen por Sucursal” ======
        $data[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '']; // separación

        // Título mini tabla
        $data[] = ['__SUBTITLE__:Resumen por Sucursal', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        // Encabezado mini tabla
        $data[] = ['SUCURSAL','TOTAL', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        // Filas HQ
        $sumHQ = 0.0;
        foreach ($byHeadquarter as $h) {
            $sumHQ += (float)$h['total'];
            $data[] = [$h['hq'], (float)$h['total'], '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        // BASE dentro del resumen
        $data[] = ['BASE', (float)$grandTotalBase, '', '', '', '', '', '', '', '', '', '', '', '', ''];

        // TOTAL final de resumen
        $data[] = ['__RSTOTAL__:TOTAL', (float)($sumHQ + $grandTotalBase), '', '', '', '', '', '', '', '', '', '', '', '', ''];

        // Guardamos rangos para estilos
        $this->summaryRowStart = $this->mainRowCount + 2 + 1; // +1 separación, +1 subtítulo; empieza realmente en el encabezado de mini tabla
        $this->summaryRowStart += 1; // saltamos el subtítulo; queda en el encabezado "SUCURSAL/TOTAL"
        $this->summaryRowEnd   = count($data);

        return $data;
    }

    /** Encabezados de la tabla principal (como en la vista) */
    public function headings(): array
    {
        $head = ['CONTROLADOR', 'PARADERO'];
        foreach (range(1,12) as $m) $head[] = $this->months[$m];
        $head[] = 'TOTAL';
        return $head;
    }

    /** Encabezados en negrita */
    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** Estética “bonita” y homóloga a la vista */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertar Título y Subtítulo arriba
                $ws->insertNewRowBefore(1, 2);
                $headerRow    = 3; // fila de headings
                $dataStartRow = 4; // primera fila de datos
                $lastRow      = $dataStartRow + $this->summaryRowEnd - 1;
                $lastCol      = 'O'; // A..O (2 + 12 + 1)

                // Título
                $ws->setCellValue('A1', "REPORTE ESTADÍSTICO DRACO {$this->year}");
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFE11D48'); // rojo como la vista
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Subtítulo
                $ws->setCellValue('A2', 'Caja > Estadístico DRACO');
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF6B7280');
                $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Header celeste (sticky look)
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE9F4FF');
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getRowDimension($headerRow)->setRowHeight(22);

                // Freeze pane (para “sticky” encabezado)
                $ws->freezePane("A{$dataStartRow}");

                // Anchos de columna (como las clases col-ctrl / col-hq / col-tot)
                $ws->getColumnDimension('A')->setWidth(26); // CONTROLADOR (izq)
                $ws->getColumnDimension('B')->setWidth(26); // PARADERO (izq)
                foreach (range('C','N') as $col) $ws->getColumnDimension($col)->setWidth(12); // meses
                $ws->getColumnDimension('O')->setWidth(14); // TOTAL

                // Bordes finos para todo el rango principal (head + datos + mini tabla)
                $ws->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCBD5E1');

                // Zebra stripes sobre el bloque principal (datos)
                $mainLastRow = $dataStartRow + $this->mainRowCount - 1;
                if ($mainLastRow >= $dataStartRow) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF8FAFC');
                    $rangeData = "A{$dataStartRow}:{$lastCol}{$mainLastRow}";
                    $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($rangeData)->setConditionalStyles($styles);
                }

                // Alineaciones: A,B a la izquierda; C..O centrado (como vista compacta)
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("C{$dataStartRow}:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Formato moneda para C..O (meses + total) en todo el bloque principal y mini tabla
                $ws->getStyle("C{$dataStartRow}:O{$lastRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                // En la mini tabla, solo la col B (TOTAL), luego ajustamos más abajo.

                // ===== Estilos especiales por marcadores =====
                for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                    $a = (string) $ws->getCell("A{$r}")->getValue();

                    // Fila cabecera de USUARIO
                    if (str_starts_with($a, '__USER__:')) {
                        $label = substr($a, 9);
                        $ws->setCellValue("A{$r}", $label);
                        // Fondo azul sólido, fuente blanca y bold
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FF0284C7');
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                        // Alineación: nombre a la izquierda, resto centrado
                        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        continue;
                    }

                    // Fila vacía (cuando no hay DRACO)
                    if ($a === '__EMPTY__') {
                        $ws->setCellValue("A{$r}", 'No hay registros DRACO para el año.');
                        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFont()->getColor()->setARGB('FF6B7280');
                        continue;
                    }

                    // Fila TOTAL GENERAL (DRACO + BASE)
                    if (str_starts_with($a, '__FOOTER__:')) {
                        $label = substr($a, 10);
                        $ws->setCellValue("A{$r}", $label);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFont()->setBold(true);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFDBEAFE');
                        $ws->getStyle("A{$r}:B{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        // Moneda ya aplicada a C..O
                        continue;
                    }

                    // Subtítulo mini tabla
                    if (str_starts_with($a, '__SUBTITLE__:')) {
                        $label = substr($a, 12);
                        $ws->setCellValue("A{$r}", $label);
                        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFont()->setBold(true)->setSize(12);
                        continue;
                    }

                    // TOTAL mini tabla
                    if (str_starts_with($a, '__RSTOTAL__:')) {
                        $ws->setCellValue("A{$r}", 'TOTAL');
                        $ws->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
                        $ws->getStyle("A{$r}:B{$r}")->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFCEE7FF');
                        // Formato moneda y derecha en B
                        $ws->getStyle("B{$r}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        continue;
                    }
                }

                // Encabezado mini tabla (“SUCURSAL / TOTAL”)
                $miniHeadRow = $dataStartRow + $this->summaryRowStart - 1; // ajustado por las 2 filas insertadas
                if ($miniHeadRow >= $dataStartRow && $miniHeadRow <= $lastRow) {
                    $ws->getStyle("A{$miniHeadRow}:B{$miniHeadRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFDBEAFE');
                    $ws->getStyle("A{$miniHeadRow}:B{$miniHeadRow}")
                        ->getFont()->setBold(true);
                    $ws->getStyle("A{$miniHeadRow}:B{$miniHeadRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Estilo celdas de la mini tabla (filas de datos)
                $miniDataStart = $miniHeadRow + 1;
                $miniLastRow   = $lastRow;
                if ($miniDataStart <= $miniLastRow) {
                    $ws->getStyle("A{$miniDataStart}:A{$miniLastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("B{$miniDataStart}:B{$miniLastRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("B{$miniDataStart}:B{$miniLastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Autofiltro solo para la tabla principal
                if ($this->mainRowCount > 0) {
                    $mainLastRow = $dataStartRow + $this->mainRowCount - 1;
                    $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$mainLastRow}");
                }
            },
        ];
    }
}
