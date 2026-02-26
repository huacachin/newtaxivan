<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RepEstDracoBaseExport implements FromArray, WithHeadings, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected int $year) {}

    // ======= Ajusta si tu campo de fecha es distinto =======
    protected string $dateColumn      = 'date'; // 'date_register' si aplica
    protected string $userModelClass  = \App\Models\User::class;

    private int $mainRowCount   = 0;  // filas del bloque principal (tabla grande)
    private int $summaryHeadRow = 0;  // fila (en dataset) del encabezado de mini tabla
    private int $summaryLastRow = 0;  // última fila total (dataset)

    private array $months = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];

    public function array(): array
    {
        // ===== Mapas de nombres frescos =====
        $userMap = [];
        $hqMap   = [];

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->select('id', 'name')
                ->orderBy('name')
                ->chunk(1000, function ($rows) use (&$userMap) {
                    foreach ($rows as $r) {
                        $userMap[(int)$r->id] = (string)$r->name;
                    }
                });
        }

        if (Schema::hasTable('headquarters')) {
            DB::table('headquarters')
                ->select('id', 'name')
                ->orderBy('name')
                ->chunk(1000, function ($rows) use (&$hqMap) {
                    foreach ($rows as $r) {
                        $hqMap[(int)$r->id] = (string)$r->name;
                    }
                });
        }

        // ===== Roles / usuarios =====
        [$controllerIds, $adminIds] = $this->loadUserIdsByRole($this->year);

        // ===== BASE por mes =====
        $baseMonthly = array_fill(1, 12, 0.0);
        $grandBase   = 0.0;

        if (Schema::hasTable('expenses')) {
            $base = DB::table('expenses')
                ->whereYear($this->dateColumn, $this->year)
                ->where('reason', 'like', '%BASE%')
                ->selectRaw('MONTH(' . $this->dateColumn . ') m, SUM(total) s')
                ->groupBy('m')
                ->pluck('s', 'm');

            foreach ($base as $m => $s) {
                $i = (int)$m;
                if ($i >= 1 && $i <= 12) {
                    $baseMonthly[$i] = (float)$s;
                    $grandBase      += (float)$s;
                }
            }
        }

        // ===== DRACO (solo controllers) =====
        $groups     = []; // uid => ['user'=>..., 'hq_rows'=>[ hid => ['hq'=>..., 'm'=>[1..12], 'total'=>...] ] ]
        $totByMonth = array_fill(1, 12, 0.0);
        $grandDraco = 0.0;
        $mkMonths   = fn () => array_fill(1, 12, 0.0);

        // Pre-seed controllers
        foreach ($controllerIds as $uid) {
            $groups[$uid] = [
                'user'    => $userMap[$uid] ?? ('User#' . $uid),
                'hq_rows' => [],
            ];
        }

        if (Schema::hasTable('expenses') && !empty($controllerIds)) {
            $rows = DB::table('expenses as e')
                ->whereYear('e.' . $this->dateColumn, $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->whereIn('e.user_id', $controllerIds)
                ->selectRaw('e.user_id, e.headquarter_id, MONTH(e.' . $this->dateColumn . ') m, SUM(e.total) s')
                ->groupBy('e.user_id', 'e.headquarter_id', 'm')
                ->get();

            foreach ($rows as $r) {
                $uid = (int)($r->user_id ?? 0);
                $hid = (int)($r->headquarter_id ?? 0);
                $mi  = max(1, min(12, (int)$r->m));
                $val = (float)$r->s;

                $groups[$uid]['hq_rows'][$hid] ??= [
                    'hq'    => $hid > 0 ? ($hqMap[$hid] ?? ('HQ#' . $hid)) : '–',
                    'm'     => $mkMonths(),
                    'total' => 0.0,
                ];

                $groups[$uid]['hq_rows'][$hid]['m'][$mi] += $val;
                $groups[$uid]['hq_rows'][$hid]['total']  += $val;

                $totByMonth[$mi] += $val;
                $grandDraco      += $val;
            }
        }

        // Controllers sin movimientos => una fila "–"
        foreach ($groups as $uid => &$g) {
            if (empty($g['hq_rows'])) {
                $g['hq_rows'][0] = [
                    'hq'    => '–',
                    'm'     => $mkMonths(),
                    'total' => 0.0,
                ];
            }
        }
        unset($g);

        // Ordenar usuarios y HQs por nombre
        uasort($groups, fn ($a, $b) => strcmp($a['user'], $b['user']));
        foreach ($groups as &$g) {
            uasort($g['hq_rows'], fn ($a, $b) => strcmp($a['hq'], $b['hq']));
        }
        unset($g);

        // ===== Combinado DRACO + BASE =====
        $combByMonth = [];
        $grandComb   = 0.0;

        for ($i = 1; $i <= 12; $i++) {
            $combByMonth[$i] = ($totByMonth[$i] ?? 0) + ($baseMonthly[$i] ?? 0);
            $grandComb      += $combByMonth[$i];
        }

        // ===== Armado dataset principal (igual a la vista) =====
        $data = [];

        // 1) Fila OFICINA / BASE
        $rowBase = ['OFICINA', 'BASE'];
        $tBase   = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $v        = (float)($baseMonthly[$m] ?? 0);
            $tBase   += $v;
            $rowBase[] = $v;
        }
        $rowBase[] = $tBase;
        $data[]    = $rowBase;

        // 2) Filas por controller / HQ (ya agruparemos con merge en AfterSheet)
        if (!empty($groups)) {
            foreach ($groups as $g) {
                $userName = mb_strtoupper($g['user'], 'UTF-8');

                foreach ($g['hq_rows'] as $row) {
                    $line   = [$userName, $row['hq']];
                    $tTotal = 0.0;

                    foreach (range(1, 12) as $m) {
                        $v       = (float)($row['m'][$m] ?? 0);
                        $tTotal += $v;
                        $line[]  = $v;
                    }

                    $line[] = $tTotal; // total por HQ
                    $data[] = $line;
                }
            }
        } else {
            // Caso sin data DRACO
            $data[] = ['__EMPTY__', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        // 3) Fila TOTAL GENERAL (DRACO + BASE)
        $footer = ['TOTAL GENERAL (DRACO + BASE)', ''];
        for ($m = 1; $m <= 12; $m++) {
            $footer[] = (float)$combByMonth[$m];
        }
        $footer[] = (float)$grandComb;
        $data[]   = $footer;

        // mainRowCount = BASE + DRACO + TOTAL GENERAL
        $this->mainRowCount = count($data);

        // ===== Mini tabla: Resumen por Sucursal =====
        // fila separador
        $data[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        // índice (1-based en dataset) donde arranca el encabezado de mini tabla
        $this->summaryHeadRow = count($data) + 1;

        // encabezado mini tabla
        $data[] = ['SUCURSAL', 'TOTAL', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        $sumHQ = 0.0;

        if (Schema::hasTable('expenses') && !empty($controllerIds)) {
            $byHQ = DB::table('expenses as e')
                ->leftJoin('headquarters as h', 'h.id', '=', 'e.headquarter_id')
                ->whereYear('e.' . $this->dateColumn, $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->whereIn('e.user_id', $controllerIds)
                ->selectRaw('COALESCE(h.name,"–") as hq_name, SUM(e.total) s')
                ->groupBy('hq_name')
                ->orderBy('hq_name')
                ->get();

            foreach ($byHQ as $h) {
                $sumHQ  += (float)$h->s;
                $data[]  = [(string)$h->hq_name, (float)$h->s, '', '', '', '', '', '', '', '', '', '', '', '', ''];
            }
        }

        // DRACO de admins sin HQ
        $adminVacuumTotal = 0.0;

        if (!empty($adminIds)) {
            $adminVacuumTotal = (float)DB::table('expenses as e')
                ->whereYear('e.' . $this->dateColumn, $this->year)
                ->where('e.reason', 'like', '%DRACO%')
                ->whereIn('e.user_id', $adminIds)
                ->sum('e.total');

            if ($adminVacuumTotal > 0) {
                $data[] = ['Sucursal vacía', (float)$adminVacuumTotal, '', '', '', '', '', '', '', '', '', '', '', '', ''];
                $sumHQ += $adminVacuumTotal;
            }
        }

        // BASE y TOTAL del resumen
        $data[] = ['BASE', (float)$grandBase, '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $data[] = ['__RSTOTAL__', (float)($sumHQ + $grandBase), '', '', '', '', '', '', '', '', '', '', '', '', ''];

        $this->summaryLastRow = count($data);

        return $data;
    }

    public function headings(): array
    {
        $head = ['CONTROLADOR', 'PARADERO'];

        foreach (range(1, 12) as $m) {
            $head[] = $this->months[$m];
        }

        $head[] = 'TOTAL';

        return $head;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Rep Est Draco';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $BLUE  = 'FF2874A6';
                $FOOT  = 'FFCEE7FF';
                $WHITE = 'FFFFFFFF';
                $black = 'FF000000';
                $gray  = 'FF808080';

                // Fuente base y alto compacto
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(13);

                // ===== Ocultar cuadrícula =====
                $ws->setShowGridLines(false);

                // Insertar 1 fila para título
                $ws->insertNewRowBefore(1, 1);
                $headerRow    = 2;
                $dataStartRow = 3;
                $lastRow      = $dataStartRow + $this->summaryLastRow - 1;
                $lastCol      = 'O'; // A..O (15 columnas)

                // ===== Título =====
                $ws->setCellValue('A1', "REPORTE ESTADÍSTICO DRACO {$this->year}");
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getRowDimension(1)->setRowHeight(20);
                $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFF80000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);

                // ===== Encabezado azul con bordes blancos + outline negro =====
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $WHITE]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BLUE]],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $WHITE]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(17);

                // Congelar bajo encabezado
                $ws->freezePane("A{$dataStartRow}");

                // ===== Anchos fijos =====
                foreach (range('A', 'O') as $c) {
                    $ws->getColumnDimension($c)->setAutoSize(false);
                }
                $ws->getColumnDimension('A')->setWidth(18.0);
                $ws->getColumnDimension('B')->setWidth(18.0);
                foreach (range('C', 'N') as $c) {
                    $ws->getColumnDimension($c)->setWidth(8.2);
                }
                $ws->getColumnDimension('O')->setWidth(10.5);

                // ===== Ocultar columnas vacías (P en adelante) =====
                foreach (range('P', 'Z') as $c) {
                    $ws->getColumnDimension($c)->setVisible(false);
                }

                // ===== Bordes datos: dotted+solid para área numérica (C-O), thin para A-B =====
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:B{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                    $ws->getStyle("C{$dataStartRow}:O{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                    ]);
                }

                // ===== Alineaciones básicas
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:O{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ===== Formato moneda para meses + total
                $ws->getStyle("C{$dataStartRow}:O{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // ===== Forzar vacíos -> 0 en la tabla principal (C..O)
                $mainLastRow = $dataStartRow + $this->mainRowCount - 1;

                for ($r = $dataStartRow; $r <= $mainLastRow; $r++) {
                    $a = (string)$ws->getCell("A{$r}")->getValue();

                    if ($a === '__EMPTY__') {
                        continue;
                    }

                    for ($col = 'C'; $col <= 'O'; $col++) {
                        $cell = $ws->getCell("{$col}{$r}");
                        $val  = $cell->getValue();

                        if ($val === '' || $val === null) {
                            $cell->setValue(0);
                        }
                    }
                }

                // ===== Fila OFICINA / BASE con fondo azul (como thead)
                $ws->getStyle("A{$dataStartRow}:B{$dataStartRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $BLUE],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // ===== Agrupar CONTROLADOR (A) y PARADERO (B) como en el Blade =====
                $firstControllerRow = $dataStartRow + 1;       // primera fila de controllers
                $scanEndRow         = $mainLastRow - 1;        // hasta antes del TOTAL GENERAL

                if ($scanEndRow >= $firstControllerRow) {
                    $firstVal = (string)$ws->getCell("A{$firstControllerRow}")->getValue();

                    // Si no hay DRACO (fila '__EMPTY__'), no agrupamos
                    if ($firstVal !== '__EMPTY__') {

                        // --- Merge de CONTROLADOR (columna A) ---
                        $blockStart  = $firstControllerRow;
                        $currentUser = $firstVal;

                        for ($row = $firstControllerRow + 1; $row <= $scanEndRow; $row++) {
                            $val = (string)$ws->getCell("A{$row}")->getValue();

                            if ($val !== $currentUser) {
                                if ($blockStart < $row - 1) {
                                    $ws->mergeCells("A{$blockStart}:A" . ($row - 1));
                                }

                                $ws->getStyle("A{$blockStart}:A" . ($row - 1))->applyFromArray([
                                    'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                                    'fill'      => [
                                        'fillType'   => Fill::FILL_SOLID,
                                        'startColor' => ['argb' => $BLUE],
                                    ],
                                    'alignment' => [
                                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                                        'vertical'   => Alignment::VERTICAL_CENTER,
                                    ],
                                ]);

                                $blockStart  = $row;
                                $currentUser = $val;
                            }
                        }

                        // Último bloque de CONTROLADOR
                        if ($blockStart <= $scanEndRow) {
                            if ($blockStart < $scanEndRow) {
                                $ws->mergeCells("A{$blockStart}:A{$scanEndRow}");
                            }

                            $ws->getStyle("A{$blockStart}:A{$scanEndRow}")->applyFromArray([
                                'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                                'fill'      => [
                                    'fillType'   => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => $BLUE],
                                ],
                                'alignment' => [
                                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                                    'vertical'   => Alignment::VERTICAL_CENTER,
                                ],
                            ]);
                        }

                        // --- Merge de PARADERO (columna B) por controller + HQ ---
                        $blockStart = $firstControllerRow;
                        $prevUser   = (string)$ws->getCell("A{$firstControllerRow}")->getValue();
                        $prevHq     = (string)$ws->getCell("B{$firstControllerRow}")->getValue();

                        for ($row = $firstControllerRow + 1; $row <= $scanEndRow; $row++) {
                            $user = (string)$ws->getCell("A{$row}")->getValue();
                            $hq   = (string)$ws->getCell("B{$row}")->getValue();

                            if ($user !== $prevUser || $hq !== $prevHq) {
                                if ($blockStart < $row - 1) {
                                    $ws->mergeCells("B{$blockStart}:B" . ($row - 1));
                                }

                                $ws->getStyle("B{$blockStart}:B" . ($row - 1))->applyFromArray([
                                    'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                                    'fill'      => [
                                        'fillType'   => Fill::FILL_SOLID,
                                        'startColor' => ['argb' => $BLUE],
                                    ],
                                    'alignment' => [
                                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                                        'vertical'   => Alignment::VERTICAL_CENTER,
                                    ],
                                ]);

                                $blockStart = $row;
                                $prevUser   = $user;
                                $prevHq     = $hq;
                            }
                        }

                        // Último bloque de HQ
                        if ($blockStart <= $scanEndRow) {
                            if ($blockStart < $scanEndRow) {
                                $ws->mergeCells("B{$blockStart}:B{$scanEndRow}");
                            }

                            $ws->getStyle("B{$blockStart}:B{$scanEndRow}")->applyFromArray([
                                'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                                'fill'      => [
                                    'fillType'   => Fill::FILL_SOLID,
                                    'startColor' => ['argb' => $BLUE],
                                ],
                                'alignment' => [
                                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                                    'vertical'   => Alignment::VERTICAL_CENTER,
                                ],
                            ]);
                        }
                    }
                }

                // ===== Estilos especiales: vacío, total general, mini tabla =====
                for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                    $a = (string)$ws->getCell("A{$r}")->getValue();

                    // Mensaje vacío
                    if ($a === '__EMPTY__') {
                        $ws->setCellValue("A{$r}", 'No hay registros DRACO para el año.');
                        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")
                            ->getFont()
                            ->getColor()
                            ->setARGB('FF6B7280');
                        continue;
                    }

                    // Pie TOTAL GENERAL (DRACO + BASE)
                    if ($a === 'TOTAL GENERAL (DRACO + BASE)') {
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'font'    => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $FOOT]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                        ]);

                        $ws->getStyle("A{$r}:B{$r}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                        // Asegura 0 en vacíos C..O
                        for ($col = 'C'; $col <= 'O'; $col++) {
                            $cell = $ws->getCell("{$col}{$r}");
                            if ($cell->getValue() === '' || $cell->getValue() === null) {
                                $cell->setValue(0);
                            }
                        }
                        continue;
                    }

                    // Total de la mini tabla
                    if ($a === '__RSTOTAL__') {
                        $ws->setCellValue("A{$r}", 'TOTAL');

                        $ws->getStyle("A{$r}:B{$r}")->applyFromArray([
                            'font'    => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $FOOT]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                        ]);

                        $ws->getStyle("B{$r}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');

                        $ws->getStyle("B{$r}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                        if ($ws->getCell("B{$r}")->getValue() === '' || $ws->getCell("B{$r}")->getValue() === null) {
                            $ws->getCell("B{$r}")->setValue(0);
                        }
                        continue;
                    }
                }

                // Mini tabla: encabezado azul
                $miniHead = $dataStartRow + ($this->summaryHeadRow - 1);

                if ($miniHead >= $dataStartRow && $miniHead <= $lastRow) {
                    $ws->getStyle("A{$miniHead}:B{$miniHead}")->applyFromArray([
                        'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                        'fill'      => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $BLUE],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // Mini tabla: datos (A izquierda, B moneda derecha) + vacíos -> 0
                $miniDataStart = $miniHead + 1;

                if ($miniDataStart <= $lastRow) {
                    $ws->getStyle("A{$miniDataStart}:A{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $ws->getStyle("B{$miniDataStart}:B{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('"S/ " #,##0.00');

                    $ws->getStyle("B{$miniDataStart}:B{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    for ($r = $miniDataStart; $r <= $lastRow; $r++) {
                        $cell = $ws->getCell("B{$r}");
                        if ($cell->getValue() === '' || $cell->getValue() === null) {
                            $cell->setValue(0);
                        }
                    }
                }
            },
        ];
    }

    /**
     * IDs por rol.
     * Para el año actual, filtra por status=active.
     * Para años pasados, no filtra status.
     */
    private function loadUserIdsByRole(int $year): array
    {
        if (
            !Schema::hasTable('roles') ||
            !Schema::hasTable('model_has_roles') ||
            !Schema::hasTable('users')
        ) {
            return [[], []];
        }

        $onlyActive = ($year === (int)Carbon::now()->year);

        $fetch = function (array $roleNames) use ($onlyActive) {
            $q = DB::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->join('users as u', 'u.id', '=', 'mr.model_id')
                ->where('mr.model_type', $this->userModelClass)
                ->whereIn('r.name', $roleNames);

            if ($onlyActive) {
                $q->where('u.status', 'active');
            }

            return $q->pluck('u.id')
                ->map(fn ($v) => (int)$v)
                ->unique()
                ->values()
                ->all();
        };

        $controllers = $fetch(['controller', 'controlador']);
        $admins      = $fetch(['admin', 'administrator', 'administrador']);

        return [$controllers, $admins];
    }
}
