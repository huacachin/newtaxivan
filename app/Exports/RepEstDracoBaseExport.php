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

class RepEstDracoBaseExport implements FromArray, ShouldAutoSize, WithHeadings, WithEvents, WithStyles
{
    public function __construct(protected int $year) {}

    // ======= Ajusta si tu campo de fecha es distinto =======
    protected string $dateColumn = 'date'; // 'date_register' si aplica
    protected string $userModelClass = \App\Models\User::class;

    private int $mainRowCount = 0;     // filas del bloque principal
    private int $summaryHeadRow = 0;   // fila (en dataset) del encabezado de mini tabla
    private int $summaryLastRow = 0;   // última fila total (dataset)

    private array $months = [
        1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
        7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE',
    ];

    public function array(): array
    {
        // ===== Mapas de nombres frescos =====
        $userMap=[]; $hqMap=[];
        if (Schema::hasTable('users')) {
            DB::table('users')->select('id','name')->orderBy('name')->chunk(1000,function($rows)use(&$userMap){
                foreach($rows as $r){ $userMap[(int)$r->id]=(string)$r->name; }
            });
        }
        if (Schema::hasTable('headquarters')) {
            DB::table('headquarters')->select('id','name')->orderBy('name')->chunk(1000,function($rows)use(&$hqMap){
                foreach($rows as $r){ $hqMap[(int)$r->id]=(string)$r->name; }
            });
        }

        // ===== Roles / usuarios =====
        [$controllerIds, $adminIds] = $this->loadUserIdsByRole($this->year);

        // ===== BASE por mes =====
        $baseMonthly = array_fill(1,12,0.0); $grandBase=0.0;
        if (Schema::hasTable('expenses')) {
            $base = DB::table('expenses')
                ->whereYear($this->dateColumn, $this->year)
                ->where('reason','like','%BASE%')
                ->selectRaw('MONTH('.$this->dateColumn.') m, SUM(total) s')
                ->groupBy('m')->pluck('s','m');

            foreach($base as $m=>$s){
                $i=(int)$m;
                if($i>=1&&$i<=12){
                    $baseMonthly[$i]=(float)$s;
                    $grandBase+=(float)$s;
                }
            }
        }

        // ===== DRACO (solo controllers) =====
        $groups=[];                                  // uid => ['user'=>..., 'hq_rows'=>[ hid => ['hq'=>..., 'm'=>[1..12], 'total'=>...] ] ]
        $totByMonth=array_fill(1,12,0.0);
        $grandDraco=0.0;
        $mkMonths=fn()=>array_fill(1,12,0.0);

        // Pre-seed: todos los controllers, aunque no tengan gastos
        foreach ($controllerIds as $uid) {
            $groups[$uid] = ['user'=>$userMap[$uid]??('User#'.$uid),'hq_rows'=>[]];
        }

        if (Schema::hasTable('expenses') && !empty($controllerIds)) {
            $rows = DB::table('expenses as e')
                ->whereYear('e.'.$this->dateColumn, $this->year)
                ->where('e.reason','like','%DRACO%')
                ->whereIn('e.user_id', $controllerIds)
                ->selectRaw('e.user_id, e.headquarter_id, MONTH(e.'.$this->dateColumn.') m, SUM(e.total) s')
                ->groupBy('e.user_id','e.headquarter_id','m')
                ->get();

            foreach($rows as $r){
                $uid=(int)($r->user_id??0);
                $hid=(int)($r->headquarter_id??0);
                $mi = max(1, min(12, (int)$r->m));
                $val=(float)$r->s;

                $groups[$uid]['hq_rows'][$hid] ??= [
                    'hq'    => $hid>0 ? ($hqMap[$hid]??('HQ#'.$hid)) : '–',
                    'm'     => $mkMonths(),
                    'total' => 0.0,
                ];
                $groups[$uid]['hq_rows'][$hid]['m'][$mi] += $val;
                $groups[$uid]['hq_rows'][$hid]['total']  += $val;

                $totByMonth[$mi] += $val;
                $grandDraco      += $val;
            }
        }

        // Para controllers sin ningún gasto, fuerza fila "–"
        foreach ($groups as $uid => &$g) {
            if (empty($g['hq_rows'])) {
                $g['hq_rows'][0] = ['hq'=>'–','m'=>$mkMonths(),'total'=>0.0];
            }
        }
        unset($g);

        // Ordenar usuarios y HQs por nombre
        uasort($groups,fn($a,$b)=>strcmp($a['user'],$b['user']));
        foreach($groups as &$g){ uasort($g['hq_rows'],fn($a,$b)=>strcmp($a['hq'],$b['hq'])); }
        unset($g);

        // ===== Combinado DRACO(controllers) + BASE =====
        $combByMonth=[]; $grandComb=0.0;
        for($i=1;$i<=12;$i++){
            $combByMonth[$i]=($totByMonth[$i]??0)+($baseMonthly[$i]??0);
            $grandComb+=$combByMonth[$i];
        }

        // ===== Armado dataset =====
        $data=[];

        // Fila Oficina / BASE
        $rowBase=['OFICINA','BASE']; $tBase=0.0;
        for($m=1;$m<=12;$m++){ $v=(float)($baseMonthly[$m]??0); $tBase+=$v; $rowBase[]=$v; }
        $rowBase[]=$tBase;
        $data[]=$rowBase;

        // Bloques por usuario controller
        if(!empty($groups)){
            foreach($groups as $g){
                // Encabezado de usuario
                $data[]=["__USER__:".mb_strtoupper($g['user']),'','','','','','','','','','','','',''];
                foreach($g['hq_rows'] as $row){
                    $line=['',$row['hq']];
                    foreach(range(1,12) as $m){ $line[]=(float)($row['m'][$m]??0); }
                    $line[]=(float)$row['total'];
                    $data[]=$line;
                }
            }
        } else {
            $data[]=['__EMPTY__','','','','','','','','','','','','','',''];
        }

        // Pie TOTAL GENERAL (DRACO controllers + BASE)
        $footer=["TOTAL GENERAL (DRACO + BASE)",''];
        for($m=1;$m<=12;$m++){ $footer[]=(float)$combByMonth[$m]; }
        $footer[]=(float)$grandComb;
        $data[]=$footer;

        $this->mainRowCount = count($data);

        // ===== Mini tabla: Resumen por Sucursal =====
        $data[]=['','','','','','','','','','','','','','','']; // separador
        $this->summaryHeadRow = count($data) + 1;
        $data[]=['SUCURSAL','TOTAL','','','','','','','','','','','','',''];

        $sumHQ=0.0;

        // Resumen por HQ SOLO controllers (JOIN para nombre correcto)
        if (Schema::hasTable('expenses') && !empty($controllerIds)) {
            $byHQ=DB::table('expenses as e')
                ->leftJoin('headquarters as h','h.id','=','e.headquarter_id')
                ->whereYear('e.'.$this->dateColumn,$this->year)
                ->where('e.reason','like','%DRACO%')
                ->whereIn('e.user_id', $controllerIds)
                ->selectRaw('COALESCE(h.name,"–") as hq_name, SUM(e.total) s')
                ->groupBy('hq_name')
                ->orderBy('hq_name')
                ->get();

            foreach($byHQ as $h){
                $sumHQ += (float)$h->s;
                $data[]=[(string)$h->hq_name,(float)$h->s,'','','','','','','','','','','','',''];
            }
        }

        // Fila "Sucursal vacía" = total DRACO de admins (activos solo en año actual)
        $adminVacuumTotal = 0.0;
        if (!empty($adminIds)) {
            $adminVacuumTotal = (float) DB::table('expenses as e')
                ->whereYear('e.'.$this->dateColumn, $this->year)
                ->where('e.reason','like','%DRACO%')
                ->whereIn('e.user_id', $adminIds)
                ->sum('e.total');

            if ($adminVacuumTotal > 0) {
                $data[]=['Sucursal vacía', (float)$adminVacuumTotal,'','','','','','','','','','','','',''];
                $sumHQ += $adminVacuumTotal;
            }
        }

        // Agrega BASE y TOTAL del resumen
        $data[]=['BASE',(float)$grandBase,'','','','','','','','','','','','',''];
        $data[]=['__RSTOTAL__',(float)($sumHQ+$grandBase),'','','','','','','','','','','','',''];

        $this->summaryLastRow = count($data);

        return $data;
    }

    public function headings(): array
    {
        $head=['CONTROLADOR','PARADERO'];
        foreach(range(1,12) as $m){ $head[]=$this->months[$m]; }
        $head[]='TOTAL';
        return $head;
    }

    public function styles(Worksheet $sheet){ return [1=>['font'=>['bold'=>true]]]; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $e){
                $ws = $e->sheet->getDelegate();

                $BLUE = 'FF2874A6';
                $FOOT = 'FFCEE7FF';
                $WHITE= 'FFFFFFFF';
                $BORD = 'FFCBD5E1';

                // Fuente base y alto compacto
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(13);

                // Insertar 1 fila para título
                $ws->insertNewRowBefore(1, 1);
                $headerRow    = 2;
                $dataStartRow = 3;
                $lastRow      = $dataStartRow + $this->summaryLastRow - 1;
                $lastCol      = 'O';

                // Título
                $ws->setCellValue('A1', "REPORTE ESTADÍSTICO DRACO {$this->year}");
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getRowDimension(1)->setRowHeight(18);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFE11D48']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Encabezado azul
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => $WHITE]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BLUE]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(16);

                // Congelar bajo encabezado
                $ws->freezePane("A{$dataStartRow}");

                // ===== Anchos “al ras” (desactivar autosize en todas) =====
                foreach (range('A','O') as $c) {
                    $ws->getColumnDimension($c)->setAutoSize(false);
                }
                // A/B (texto) compactos pero legibles
                $ws->getColumnDimension('A')->setWidth(18.0); // CONTROLADOR
                $ws->getColumnDimension('B')->setWidth(18.0); // PARADERO
                // Meses C..N súper compactos (montos)
                foreach (range('C','N') as $c) {
                    $ws->getColumnDimension($c)->setWidth(8.2);
                }
                // TOTAL (O) un poco más ancho
                $ws->getColumnDimension('O')->setWidth(10.5);

                // ===== Bordes finos
                $ws->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($BORD);

                // ===== Alineaciones “al ras”
                if ($lastRow >= $dataStartRow){
                    // Texto a la izquierda (con shrink para evitar wraps)
                    $ws->getStyle("A{$dataStartRow}:A{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true);
                    $ws->getStyle("B{$dataStartRow}:B{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true);

                    // Montos a la derecha
                    $ws->getStyle("C{$dataStartRow}:O{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ===== Formatos moneda para meses + total
                $ws->getStyle("C{$dataStartRow}:O{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // ===== Forzar vacíos -> 0 en la tabla principal (C..O)
                $mainLastRow = $dataStartRow + $this->mainRowCount - 1;
                for ($r = $dataStartRow; $r <= $mainLastRow; $r++) {
                    $a = (string)$ws->getCell("A{$r}")->getValue();
                    if (str_starts_with($a,'__USER__') || $a==='__EMPTY__') { continue; }
                    for ($col = 'C'; $col <= 'O'; $col++) {
                        $cell = $ws->getCell("{$col}{$r}");
                        $val  = $cell->getValue();
                        if ($val === '' || $val === null) { $cell->setValue(0); }
                    }
                }

                // ===== Estilos especiales (usuario, vacío, total general, mini tabla total)
                for ($r=$dataStartRow; $r<=$lastRow; $r++){
                    $a = (string)$ws->getCell("A{$r}")->getValue();

                    // Cabecera de usuario
                    if (str_starts_with($a,'__USER__:')){
                        $ws->setCellValue("A{$r}", substr($a,9));
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => $WHITE]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BLUE]],
                        ]);
                        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        continue;
                    }

                    // Mensaje vacío
                    if ($a==='__EMPTY__'){
                        $ws->setCellValue("A{$r}", 'No hay registros DRACO para el año.');
                        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->getColor()->setARGB('FF6B7280');
                        continue;
                    }

                    // Pie TOTAL GENERAL
                    if ($a==='TOTAL GENERAL (DRACO + BASE)'){
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $FOOT]],
                        ]);
                        $ws->getStyle("A{$r}:B{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        // Asegura 0 en vacíos C..O
                        for ($col='C'; $col<='O'; $col++){
                            $cell=$ws->getCell("{$col}{$r}");
                            if ($cell->getValue()==='' || $cell->getValue()===null){ $cell->setValue(0); }
                        }
                        continue;
                    }

                    // Total de la mini tabla
                    if ($a==='__RSTOTAL__'){
                        $ws->setCellValue("A{$r}", 'TOTAL');
                        $ws->getStyle("A{$r}:B{$r}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $FOOT]],
                        ]);
                        $ws->getStyle("B{$r}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                        $ws->getStyle("B{$r}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        if ($ws->getCell("B{$r}")->getValue()==='' || $ws->getCell("B{$r}")->getValue()===null){
                            $ws->getCell("B{$r}")->setValue(0);
                        }
                        continue;
                    }
                }

                // Mini tabla: encabezado azul
                $miniHead = $dataStartRow + ($this->summaryHeadRow - 1);
                if ($miniHead >= $dataStartRow && $miniHead <= $lastRow){
                    $ws->getStyle("A{$miniHead}:B{$miniHead}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => $WHITE]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $BLUE]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Mini tabla: datos (A izquierda, B moneda derecha) + vacíos -> 0
                $miniDataStart = $miniHead + 1;
                if ($miniDataStart <= $lastRow){
                    $ws->getStyle("A{$miniDataStart}:A{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true);
                    $ws->getStyle("B{$miniDataStart}:B{$lastRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("B{$miniDataStart}:B{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    for ($r=$miniDataStart; $r<=$lastRow; $r++){
                        $cell=$ws->getCell("B{$r}");
                        if ($cell->getValue()==='' || $cell->getValue()===null){ $cell->setValue(0); }
                    }
                }
            },
        ];
    }


    /** IDs por rol. Para el año actual, filtra por status=active. Para años pasados, no filtra status. */
    private function loadUserIdsByRole(int $year): array
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('model_has_roles') || !Schema::hasTable('users')) {
            return [[],[]];
        }

        $onlyActive = ($year === (int) Carbon::now()->year);

        $fetch = function(array $roleNames) use ($onlyActive){
            $q = DB::table('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->join('users as u', 'u.id', '=', 'mr.model_id')
                ->where('mr.model_type', $this->userModelClass)
                ->whereIn('r.name', $roleNames);

            if ($onlyActive) {
                $q->where('u.status', 'active');
            }

            return $q->pluck('u.id')->map(fn($v)=>(int)$v)->unique()->values()->all();
        };

        $controllers = $fetch(['controller','controlador']);
        $admins      = $fetch(['admin','administrator','administrador']);

        return [$controllers, $admins];
    }
}
