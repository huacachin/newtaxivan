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

    private int $mainRowCount = 0;     // filas del bloque principal
    private int $summaryHeadRow = 0;   // fila (en dataset) del encabezado de mini tabla
    private int $summaryLastRow = 0;   // última fila total (dataset)

    private array $months = [
        1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',
        7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE',
    ];

    public function array(): array
    {
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

        // BASE
        $baseMonthly = array_fill(1,12,0.0); $grandBase=0.0;
        if (Schema::hasTable('expenses')) {
            $base = DB::table('expenses')
                ->whereYear('date',$this->year)->where('reason','like','%BASE%')
                ->selectRaw('MONTH(date) m, SUM(total) s')->groupBy('m')->pluck('s','m');
            foreach($base as $m=>$s){ $i=(int)$m; if($i>=1&&$i<=12){ $baseMonthly[$i]=(float)$s; $grandBase+=(float)$s; } }
        }

        // DRACO
        $groups=[]; $totByMonth=array_fill(1,12,0.0); $grandDraco=0.0;
        if (Schema::hasTable('expenses')) {
            $rows = DB::table('expenses as e')->whereYear('e.date',$this->year)
                ->where('e.reason','like','%DRACO%')
                ->selectRaw('e.user_id, e.headquarter_id, MONTH(e.date) m, SUM(e.total) s')
                ->groupBy('e.user_id','e.headquarter_id','m')->get();
            $mk=fn()=>array_fill(1,12,0.0);
            foreach($rows as $r){
                $uid=(int)($r->user_id??0); $hid=(int)($r->headquarter_id??0);
                $mi=max(1,min(12,(int)$r->m)); $val=(float)$r->s;
                $groups[$uid] ??= ['user'=>$userMap[$uid]??'-','hq_rows'=>[]];
                $groups[$uid]['hq_rows'][$hid] ??= ['hq'=>$hqMap[$hid]??($hid?'HQ#'.$hid:'-'),'m'=>$mk(),'total'=>0.0];
                $groups[$uid]['hq_rows'][$hid]['m'][$mi]+=$val;
                $groups[$uid]['hq_rows'][$hid]['total']+=$val;
                $totByMonth[$mi]+=$val; $grandDraco+=$val;
            }
            uasort($groups,fn($a,$b)=>strcmp($a['user'],$b['user']));
            foreach($groups as &$g){ uasort($g['hq_rows'],fn($a,$b)=>strcmp($a['hq'],$b['hq'])); }
            unset($g);
        }

        // Combinado DRACO + BASE (pie)
        $combByMonth=[]; $grandComb=0.0;
        for($i=1;$i<=12;$i++){ $combByMonth[$i]=($totByMonth[$i]??0)+($baseMonthly[$i]??0); $grandComb+=$combByMonth[$i]; }

        // ===== Tabla principal =====
        $data=[];

        // Oficina / Base
        $rowBase=['OFICINA','BASE']; $tBase=0.0;
        for($m=1;$m<=12;$m++){ $v=(float)($baseMonthly[$m]??0); $tBase+=$v; $rowBase[]=$v; }
        $rowBase[]=$tBase; $data[]=$rowBase;

        // Usuarios
        if(!empty($groups)){
            foreach($groups as $g){
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

        // Pie TOTAL GENERAL
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
        if (Schema::hasTable('expenses')) {
            $byHQ=DB::table('expenses as e')->whereYear('e.date',$this->year)
                ->where('e.reason','like','%DRACO%')
                ->selectRaw('e.headquarter_id, SUM(e.total) s')->groupBy('e.headquarter_id')->get();
            $tmp=[];
            foreach($byHQ as $h){
                $tmp[]=['hq'=>$hqMap[(int)$h->headquarter_id]??((int)$h->headquarter_id?'HQ#'.(int)$h->headquarter_id:'-'),'total'=>(float)$h->s];
            }
            usort($tmp,fn($a,$b)=>$b['total']<=>$a['total']);
            foreach($tmp as $row){ $sumHQ+=$row['total']; $data[]=[$row['hq'],(float)$row['total'],'','','','','','','','','','','','','']; }
        }
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

                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Insertar 1 fila para título
                $ws->insertNewRowBefore(1, 1);
                $headerRow    = 2;
                $dataStartRow = 3;
                $lastRow      = $dataStartRow + $this->summaryLastRow - 1;
                $lastCol      = 'O';

                // Título
                $ws->setCellValue('A1', "REPORTE ESTADÍSTICO DRACO {$this->year}");
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFE11D48');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Encabezado azul
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BLUE);
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()
                    ->setBold(true)->getColor()->setARGB($WHITE);
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getRowDimension($headerRow)->setRowHeight(20);

                $ws->freezePane("A{$dataStartRow}");

                // Anchos compactos
                $ws->getColumnDimension('A')->setWidth(18);
                $ws->getColumnDimension('B')->setWidth(18);
                foreach(range('C','N') as $c){ $ws->getColumnDimension($c)->setWidth(9); }
                $ws->getColumnDimension('O')->setWidth(10);

                // Bordes
                $ws->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($BORD);

                // Alineaciones
                if ($lastRow >= $dataStartRow){
                    $ws->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("C{$dataStartRow}:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Formatos moneda para meses + total
                $ws->getStyle("C{$dataStartRow}:O{$lastRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                // ===== Forzar vacíos -> 0 en la tabla principal (C..O) =====
                $mainLastRow = $dataStartRow + $this->mainRowCount - 1;
                for ($r = $dataStartRow; $r <= $mainLastRow; $r++) {
                    $a = (string)$ws->getCell("A{$r}")->getValue();
                    if (str_starts_with($a,'__USER__') || $a==='__EMPTY__') { continue; } // saltar filas de título de usuario / mensaje
                    for ($col = 'C'; $col <= 'O'; $col++) {
                        $cell = $ws->getCell("{$col}{$r}");
                        $val  = $cell->getValue();
                        if ($val === '' || $val === null) { $cell->setValue(0); }
                    }
                }

                // ===== Estilos especiales =====
                for ($r=$dataStartRow; $r<=$lastRow; $r++){
                    $a = (string)$ws->getCell("A{$r}")->getValue();

                    // Cabecera de usuario
                    if (str_starts_with($a,'__USER__:')){
                        $ws->setCellValue("A{$r}", substr($a,9));
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BLUE);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFont()
                            ->setBold(true)->getColor()->setARGB($WHITE);
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
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
                        $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($FOOT);
                        $ws->getStyle("A{$r}:B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        // asegurar ceros en C..O del pie
                        for ($col='C'; $col<='O'; $col++){
                            $cell=$ws->getCell("{$col}{$r}");
                            if ($cell->getValue()==='' || $cell->getValue()===null){ $cell->setValue(0); }
                        }
                        continue;
                    }

                    // Total de la mini tabla
                    if ($a==='__RSTOTAL__'){
                        $ws->setCellValue("A{$r}", 'TOTAL');
                        $ws->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
                        $ws->getStyle("A{$r}:B{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($FOOT);
                        $ws->getStyle("B{$r}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        if ($ws->getCell("B{$r}")->getValue()==='' || $ws->getCell("B{$r}")->getValue()===null){
                            $ws->getCell("B{$r}")->setValue(0);
                        }
                        continue;
                    }
                }

                // Mini tabla: encabezado azul
                $miniHead = $dataStartRow + ($this->summaryHeadRow - 1);
                if ($miniHead >= $dataStartRow && $miniHead <= $lastRow){
                    $ws->getStyle("A{$miniHead}:B{$miniHead}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BLUE);
                    $ws->getStyle("A{$miniHead}:B{$miniHead}")->getFont()
                        ->setBold(true)->getColor()->setARGB($WHITE);
                    $ws->getStyle("A{$miniHead}:B{$miniHead}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Mini tabla: datos moneda + vacíos -> 0
                $miniDataStart = $miniHead + 1;
                if ($miniDataStart <= $lastRow){
                    $ws->getStyle("A{$miniDataStart}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("B{$miniDataStart}:B{$lastRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getStyle("B{$miniDataStart}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    for ($r=$miniDataStart; $r<=$lastRow; $r++){
                        $cell=$ws->getCell("B{$r}");
                        if ($cell->getValue()==='' || $cell->getValue()===null){ $cell->setValue(0); }
                    }
                }

                // Sin filtros, sin zebra.
            },
        ];
    }
}
