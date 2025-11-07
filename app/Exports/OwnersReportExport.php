<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class OwnersReportExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected ?string $search = null,
        protected string  $filter = 'plate' // plate|name|code
    ) {}

    /** Correlativo para el primer cuadro (activos) */
    private int $rowNum = 0;

    /* ==================== PRIMER CUADRO (ACTIVOS) ==================== */
    public function query(): Builder
    {
        $s    = trim((string)$this->search);
        $like = $s === '' ? null : '%'.str_replace(['%','_'], ['\%','\_'], $s).'%';

        return DB::table('owners as o')
            ->join('vehicles as v', function ($j) {
                $j->on('v.owner_id','=','o.id')
                    ->whereIn(DB::raw('LOWER(TRIM(v.status))'), ['active','activo']);
            })
            ->when($like !== null, function ($q) use ($like) {
                if ($this->filter === 'name')  $q->where('o.name','like',$like);
                if ($this->filter === 'code')  $q->where('o.id','like',$like);
                if ($this->filter === 'plate') $q->where('v.plate','like',$like);
            })
            ->select([
                'o.id',
                'o.name',
                'v.plate',
                'o.document_number',
                'o.phone',
                'v.sort_order',
            ])
            ->orderByRaw('v.sort_order IS NULL, v.sort_order ASC')
            ->orderBy('v.plate');
    }

    public function headings(): array
    {
        // Orden nuevo: A..E
        return ['ID','Placa','Nombre','N° Documento','Teléfono'];
    }

    public function map($r): array
    {
        // Orden nuevo: ID, Placa, Nombre, Documento, Teléfono
        return [
            ++$this->rowNum,                       // ID correlativo
            strtoupper((string)$r->plate),         // Placa (B)
            (string)$r->name,                      // Nombre (C)
            (string)$r->document_number,           // N° Documento (D) - texto
            (string)$r->phone,                     // Teléfono (E) - texto
        ];
    }

    public function columnFormats(): array
    {
        // D: N° Documento (texto), E: Teléfono (texto)
        return [
            'D' => '@',
            'E' => '@',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /* ==================== DISEÑO: 2 CUADROS EN UNA HOJA ==================== */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Fuente global 10pt
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Paleta
                $blue     = 'FF2874A6'; // #2874A6
                $footerBg = 'FFCEE7FF'; // #CEE7FF
                $white    = 'FFFFFFFF';
                $borderC  = 'FFCFD8DC';
                $black    = '000000';

                /* ---------- Título (sin subtítulo) ---------- */
                $ws->insertNewRowBefore(1, 2);

                // Índices de tabla 1
                $head1  = 3;  // THEAD
                $start1 = 4;  // datos
                $last   = (int)$ws->getHighestRow();

                // Título con placeholder (actualizamos al final)
                $ws->setCellValue('A1','LISTADO GENERAL DE PROPIETARIO ( 0 )');
                $ws->mergeCells('A1:E1');
                $ws->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$black]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(18);

                // Fila 2 vacía (subtítulo retirado)
                $ws->mergeCells('A2:E2');
                $ws->setCellValue('A2', '');
                $ws->getRowDimension(2)->setRowHeight(4);

                /* ---------- Encabezado + cuerpo del PRIMER cuadro (ACTIVOS) ---------- */
                // THEAD azul
                $ws->getStyle("A{$head1}:E{$head1}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($head1)->setRowHeight(16);

                // Congelar encabezado
                $ws->freezePane("A{$start1}");

                $last = (int)$ws->getHighestRow();
                $hasData1 = $last >= $start1;
                $end1     = $hasData1 ? $last : ($start1 - 1);

                // Sin AutoFilter (pediste quitar filtros automáticos SOLO aquí)
                if ($hasData1) {
                    $ws->getStyle("A{$head1}:E{$end1}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB($borderC);

                    $range1 = "A{$start1}:E{$end1}";
                    $z1 = new Conditional();
                    $z1->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $z1->setConditions(['MOD(ROW(),2)=0']);
                    $z1->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF3F4F6');
                    $styles1 = $ws->getStyle($range1)->getConditionalStyles();
                    $styles1[] = $z1;
                    $ws->getStyle($range1)->setConditionalStyles($styles1);

                    // Alineaciones con nuevo orden
                    $ws->getStyle("A{$start1}:A{$end1}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ID
                    $ws->getStyle("B{$start1}:B{$end1}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false); // Placa
                    $ws->getStyle("C{$start1}:C{$end1}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(false);   // Nombre
                    $ws->getStyle("D{$start1}:E{$end1}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(false);   // Doc / Tel
                }

                // Pie (TOTAL ACTIVOS)
                $foot1 = $end1 + 1;
                $countActive = $hasData1 ? ($end1 - $start1 + 1) : 0;
                $ws->mergeCells("A{$foot1}:D{$foot1}");
                $ws->setCellValue("A{$foot1}", 'TOTAL ACTIVOS');
                $ws->setCellValue("E{$foot1}", $countActive);
                $ws->getStyle("A{$foot1}:E{$foot1}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>'FF000000']],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                ]);
                $ws->getStyle("A{$foot1}:D{$foot1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("E{$foot1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                /* ---------- SEGUNDO cuadro (LIBRES) ---------- */
                $title2 = $foot1 + 2;
                $ws->setCellValue("A{$title2}", 'PROPIETARIOS LIBRES');
                $ws->mergeCells("A{$title2}:E{$title2}");
                $ws->getStyle("A{$title2}:E{$title2}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($title2)->setRowHeight(16);

                // Header 2
                $head2  = $title2 + 1;
                $ws->fromArray($this->headings(), null, "A{$head2}");
                $ws->getStyle("A{$head2}:E{$head2}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension($head2)->setRowHeight(16);

                // Data libres
                $start2 = $head2 + 1;
                $freeRows = $this->fetchFreeOwners(); // ya con nuevo orden
                $countFree = count($freeRows);

                if ($countFree > 0) {
                    $ws->fromArray($freeRows, null, "A{$start2}");
                    $end2 = $start2 + $countFree - 1;

                    // Bordes + gris suave
                    $ws->getStyle("A{$head2}:E{$end2}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB($borderC);
                    $ws->getStyle("A{$start2}:E{$end2}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE5E7EB');

                    // Alineaciones (nuevo orden)
                    $ws->getStyle("A{$start2}:A{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ID
                    $ws->getStyle("B{$start2}:B{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(false); // Placa (—)
                    $ws->getStyle("C{$start2}:C{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(false); // Nombre
                    $ws->getStyle("D{$start2}:E{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(false); // Doc/Tel

                    // Totales 2
                    $foot2 = $end2 + 1;
                    $ws->mergeCells("A{$foot2}:D{$foot2}");
                    $ws->setCellValue("A{$foot2}", 'TOTAL LIBRES');
                    $ws->setCellValue("E{$foot2}", $countFree);
                    $ws->getStyle("A{$foot2}:E{$foot2}")->applyFromArray([
                        'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>'FF000000']],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                        'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                    ]);
                    $ws->getStyle("A{$foot2}:D{$foot2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("E{$foot2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $msgRow = $start2;
                    $ws->mergeCells("A{$msgRow}:E{$msgRow}");
                    $ws->setCellValue("A{$msgRow}", 'No hay propietarios libres para los filtros actuales.');
                    $ws->getStyle("A{$msgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // === Actualizar título con cantidad total (activos + libres) ===
                $totalOwners = $countActive + $countFree;
                $ws->setCellValue('A1', "LISTADO GENERAL DE PROPIETARIO ( {$totalOwners} )");

                /* ---------- Anchos mínimos (compacto real, sin shrink) ---------- */
                foreach (['A','B','C','D','E'] as $col) {
                    $ws->getColumnDimension($col)->setAutoSize(false);
                }
                // A:ID  B:Placa  C:Nombre  D:N° Documento  E:Teléfono
                $ws->getColumnDimension('A')->setWidth(4.2);
                $ws->getColumnDimension('B')->setWidth(9.5);
                $ws->getColumnDimension('C')->setWidth(20.0);
                $ws->getColumnDimension('D')->setWidth(12.0);
                $ws->getColumnDimension('E')->setWidth(11.0);

                // Evitar aire extra
                $lastRow = (int) $ws->getHighestRow();
                $ws->getStyle("A1:E{$lastRow}")
                    ->getAlignment()->setWrapText(false)->setIndent(0);
            },
        ];
    }

    /** “Propietarios libres” mapeados a las 5 columnas pedidas, con ID correlativo propio. */
    private function fetchFreeOwners(): array
    {
        $s    = trim((string)$this->search);
        $like = $s === '' ? null : '%'.str_replace(['%','_'], ['\%','\_'], $s).'%';

        $rows = DB::table('owners as o')
            ->whereNotExists(function ($sub) {
                $sub->from('vehicles as v')
                    ->whereColumn('v.owner_id','=','o.id')
                    ->whereIn(DB::raw('LOWER(TRIM(v.status))'), ['active','activo']);
            })
            ->when($like !== null && $this->filter === 'name', fn($qq)=>$qq->where('o.name','like',$like))
            ->when($like !== null && $this->filter === 'code', fn($qq)=>$qq->where('o.id','like',$like))
            ->orderBy('o.name')
            ->select(['o.id','o.name','o.document_number','o.phone'])
            ->get();

        $out = [];
        $i = 1; // correlativo del segundo cuadro
        foreach ($rows as $r) {
            $out[] = [
                $i++,                        // A: ID correlativo
                '—',                         // B: Placa (libre)
                (string)$r->name,            // C: Nombre
                (string)$r->document_number, // D: N° Documento
                (string)$r->phone,           // E: Teléfono
            ];
        }
        return $out;
    }
}
