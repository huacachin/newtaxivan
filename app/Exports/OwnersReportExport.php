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

class OwnersReportExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected ?string $search = null,
        protected string  $filter = 'plate'
    ) {}

    private int $rowNum = 0;

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
            ->select(['o.id','o.name','v.plate','o.document_number','o.phone','v.sort_order'])
            ->orderByRaw('v.sort_order IS NULL, v.sort_order ASC')
            ->orderBy('v.plate');
    }

    public function headings(): array
    {
        return ['Item','Cod','Placa','Nombre','N° Documento','Teléfono'];
    }

    public function map($r): array
    {
        return [
            ++$this->rowNum,                           // Item
            $r->sort_order ?? $r->id,                  // Cod (sort_order, fallback id)
            strtoupper((string)$r->plate),             // Placa
            (string)$r->name,                          // Nombre
            (string)$r->document_number,               // N° Documento
            (string)$r->phone,                         // Teléfono
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => '@', // N° Documento
            'F' => '@', // Teléfono
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $e->sheet->getDelegate()->setTitle('Propietarios');
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $gray     = 'FF808080';
                $red      = 'FFF80000';

                $ws->getDefaultRowDimension()->setRowHeight(15);

                // ===== Insertar fila de título =====
                $ws->insertNewRowBefore(1, 1);

                $head1  = 2;
                $start1 = 3;

                // ===== Título (placeholder, se actualiza al final) =====
                $ws->setCellValue('A1', 'LISTADO GENERAL DE PROPIETARIO ( 0 )');
                $ws->mergeCells('A1:F1');
                $ws->getStyle('A1:F1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                // ===== Ocultar cuadrícula =====
                $ws->setShowGridLines(false);

                // ===== Encabezado sección 1 =====
                $ws->getStyle("A{$head1}:F{$head1}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $white]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension($head1)->setRowHeight(16);

                $last     = (int) $ws->getHighestRow();
                $hasData1 = $last >= $start1;
                $end1     = $hasData1 ? $last : ($start1 - 1);

                if ($hasData1) {
                    // Bordes datos sección 1
                    $ws->getStyle("A{$start1}:F{$end1}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    $ws->getStyle("A{$start1}:B{$end1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$start1}:C{$end1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$start1}:D{$end1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("E{$start1}:F{$end1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // ===== TOTAL ACTIVOS =====
                $foot1       = $end1 + 1;
                $countActive = $hasData1 ? ($end1 - $start1 + 1) : 0;
                $ws->mergeCells("A{$foot1}:E{$foot1}");
                $ws->setCellValue("A{$foot1}", 'TOTAL ACTIVOS');
                $ws->setCellValue("F{$foot1}", $countActive);
                $ws->getStyle("A{$foot1}:F{$foot1}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension($foot1)->setRowHeight(16);

                // ===== Sección 2: PROPIETARIOS LIBRES =====
                $title2 = $foot1 + 2;
                $ws->mergeCells("A{$title2}:F{$title2}");
                $ws->setCellValue("A{$title2}", 'PROPIETARIOS LIBRES');
                $ws->getStyle("A{$title2}:F{$title2}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                ]);
                $ws->getRowDimension($title2)->setRowHeight(16);

                // ===== Encabezado sección 2 =====
                $head2 = $title2 + 1;
                $ws->fromArray($this->headings(), null, "A{$head2}");
                $ws->getStyle("A{$head2}:F{$head2}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    'borders'   => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $white]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension($head2)->setRowHeight(16);

                // ===== Datos sección 2 =====
                $start2    = $head2 + 1;
                $freeRows  = $this->fetchFreeOwners();
                $countFree = count($freeRows);

                if ($countFree > 0) {
                    $ws->fromArray($freeRows, null, "A{$start2}");
                    $end2 = $start2 + $countFree - 1;

                    $ws->getStyle("A{$start2}:F{$end2}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    $ws->getStyle("A{$start2}:B{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$start2}:C{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$start2}:D{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("E{$start2}:F{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                } else {
                    $ws->mergeCells("A{$start2}:F{$start2}");
                    $ws->setCellValue("A{$start2}", 'No hay propietarios libres para los filtros actuales.');
                    $ws->getStyle("A{$start2}:F{$start2}")->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]]],
                    ]);
                }

                // ===== Actualizar total en título =====
                $totalOwners = $countActive + $countFree;
                $ws->setCellValue('A1', "LISTADO GENERAL DE PROPIETARIO ( {$totalOwners} )");

                // ===== Anchos de columna =====
                foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                    $ws->getColumnDimension($col)->setAutoSize(false);
                }
                $ws->getColumnDimension('A')->setWidth(4.2);
                $ws->getColumnDimension('B')->setWidth(5.2);
                $ws->getColumnDimension('C')->setWidth(9.5);
                $ws->getColumnDimension('D')->setWidth(30.0);
                $ws->getColumnDimension('E')->setWidth(12.0);
                $ws->getColumnDimension('F')->setWidth(11.0);

                // ===== Ocultar columnas vacías (G en adelante) =====
                foreach (range('G', 'Z') as $col) {
                    $ws->getColumnDimension($col)->setVisible(false);
                }

                // ===== Fuente 10 global =====
                $lastRow = (int) $ws->getHighestRow();
                $ws->getStyle("A1:F{$lastRow}")->getFont()->setSize(10);
                $ws->getStyle("A1:F{$lastRow}")->getAlignment()->setWrapText(false);
            },
        ];
    }


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
        $i = 1;
        foreach ($rows as $r) {
            $out[] = [
                $i++,                  // Item
                '—',                   // Cod (sin vehículo)
                '—',                   // Placa (sin vehículo)
                (string)$r->name,      // Nombre
                (string)$r->document_number,
                (string)$r->phone,
            ];
        }
        return $out;
    }

}
