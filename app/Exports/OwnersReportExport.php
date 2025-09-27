<?php

namespace App\Exports;

use Carbon\Carbon;
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
                'o.id','o.name','o.document_type','o.document_number',
                'o.document_expiration_date','o.birthdate','o.address','o.district',
                'o.email','o.phone',
                'v.plate'
            ])
            ->orderBy('o.name')
            ->orderBy('v.plate');
    }

    public function headings(): array
    {
        // 11 columnas (A..K)
        return [
            'ID',
            'Nombre',
            'Tipo Doc.',
            'N° Documento',
            'Venc. Documento',
            'Nacimiento',
            'Dirección',
            'Distrito',
            'Email',
            'Teléfono',
            'Placa (activa)',
        ];
    }

    public function map($r): array
    {
        return [
            $r->id,
            (string)$r->name,
            strtoupper((string)$r->document_type),
            (string)$r->document_number, // texto
            !empty($r->document_expiration_date) ? Carbon::parse($r->document_expiration_date) : null,
            !empty($r->birthdate) ? Carbon::parse($r->birthdate) : null,
            (string)$r->address,
            (string)$r->district,
            (string)$r->email,
            (string)$r->phone, // texto
            strtoupper((string)$r->plate),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '@',                                 // N° Doc (texto)
            'E' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Venc
            'F' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Nac
            'J' => '@',                                 // Teléfono
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

                /* ---------- Título / Subtítulo ---------- */
                $ws->insertNewRowBefore(1, 2);

                $ws->setCellValue('A1','REPORTE DE PROPIETARIOS');
                $ws->mergeCells('A1:K1');
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(1)->setRowHeight(24);

                $filters = [];
                if (trim((string)$this->search) !== '') {
                    $filters[] = ([
                            'name'  => 'Nombre',
                            'code'  => 'Código',
                            'plate' => 'Placa',
                        ][$this->filter] ?? 'Búsqueda') . ': ' . $this->search;
                }
                $ws->setCellValue('A2', implode(' · ', $filters) ?: 'Incluye “Propietarios libres” como segundo cuadro.');
                $ws->mergeCells('A2:K2');
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $ws->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(2)->setRowHeight(18);

                /* ---------- Encabezado + cuerpo del PRIMER cuadro (ACTIVOS) ---------- */
                $head1  = 3;           // Encabezado activo
                $start1 = 4;           // Inicio de filas activas
                $last   = (int)$ws->getHighestRow();

                // thead oscuro
                $ws->getStyle("A{$head1}:K{$head1}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$head1}:K{$head1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getRowDimension($head1)->setRowHeight(20);
                $ws->getStyle("A{$head1}:K{$head1}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');

                // Congelar encabezado
                $ws->freezePane("A{$start1}");

                $hasData1 = $last >= $start1;
                $end1     = $hasData1 ? $last : ($start1 - 1);

                // Bordes, zebra y autofiltro (solo primer cuadro)
                if ($hasData1) {
                    $ws->setAutoFilter("A{$head1}:K{$end1}");
                    $ws->getStyle("A{$head1}:K{$end1}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFCFD8DC');

                    $range1 = "A{$start1}:K{$end1}";
                    $z1 = new Conditional();
                    $z1->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $z1->setConditions(['MOD(ROW(),2)=0']);
                    $z1->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
                    $styles1 = $ws->getStyle($range1)->getConditionalStyles();
                    $styles1[] = $z1;
                    $ws->getStyle($range1)->setConditionalStyles($styles1);
                }

                // Totales del primer cuadro
                $foot1 = $end1 + 1;
                $ws->mergeCells("A{$foot1}:J{$foot1}");
                $ws->setCellValue("A{$foot1}", 'TOTAL ACTIVOS');
                if ($hasData1) {
                    $ws->setCellValue("K{$foot1}", "=COUNTA(A{$start1}:A{$end1})");
                } else {
                    $ws->setCellValue("K{$foot1}", 0);
                }
                $ws->getStyle("A{$foot1}:K{$foot1}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$foot1}:K{$foot1}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                $ws->getStyle("A{$foot1}:K{$foot1}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $ws->getStyle("A{$foot1}:J{$foot1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("K{$foot1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                /* ---------- SEGUNDO cuadro (LIBRES) debajo del primero ---------- */
                $title2 = $foot1 + 2;              // Título del segundo cuadro
                $ws->setCellValue("A{$title2}", 'PROPIETARIOS LIBRES');
                $ws->mergeCells("A{$title2}:K{$title2}");
                $ws->getStyle("A{$title2}")->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$title2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle("A{$title2}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension($title2)->setRowHeight(20);

                // Head del segundo cuadro
                $head2  = $title2 + 1;
                $ws->fromArray($this->headings(), null, "A{$head2}");
                $ws->getStyle("A{$head2}:K{$head2}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$head2}:K{$head2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle("A{$head2}:K{$head2}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');

                // Data libres
                $start2 = $head2 + 1;
                $freeRows = $this->fetchFreeOwners(); // array de arrays (mapeados)
                if (!empty($freeRows)) {
                    $ws->fromArray($freeRows, null, "A{$start2}");

                    $end2 = $start2 + count($freeRows) - 1;

                    // Bordes en segundo cuadro
                    $ws->getStyle("A{$head2}:K{$end2}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFCFD8DC');

                    // Fondo gris más fuerte SOLO al cuerpo del segundo cuadro
                    $ws->getStyle("A{$start2}:K{$end2}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE5E7EB');

                    // Alineaciones
                    $ws->getStyle("A{$start2}:A{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("C{$start2}:C{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$start2}:D{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("E{$start2}:F{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("G{$start2}:J{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("K{$start2}:K{$end2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Totales del segundo cuadro
                    $foot2 = $end2 + 1;
                    $ws->mergeCells("A{$foot2}:J{$foot2}");
                    $ws->setCellValue("A{$foot2}", 'TOTAL LIBRES');
                    $ws->setCellValue("K{$foot2}", count($freeRows));
                    $ws->getStyle("A{$foot2}:K{$foot2}")
                        ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                    $ws->getStyle("A{$foot2}:K{$foot2}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                    $ws->getStyle("A{$foot2}:K{$foot2}")
                        ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                    $ws->getStyle("A{$foot2}:J{$foot2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("K{$foot2}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    // Sin libres: un mensajito
                    $msgRow = $start2;
                    $ws->mergeCells("A{$msgRow}:K{$msgRow}");
                    $ws->setCellValue("A{$msgRow}", 'No hay propietarios libres para los filtros actuales.');
                    $ws->getStyle("A{$msgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                /* ---------- Anchos compactos (como siempre) ---------- */
                $this->compactWidths($ws);
            },
        ];
    }

    /** Obtiene “Propietarios libres” y los mapea a las mismas 11 columnas. */
    private function fetchFreeOwners(): array
    {
        $s    = trim((string)$this->search);
        $like = $s === '' ? null : '%'.str_replace(['%','_'], ['\%','\_'], $s).'%';

        $q = DB::table('owners as o')
            ->whereNotExists(function ($sub) {
                $sub->from('vehicles as v')
                    ->whereColumn('v.owner_id','=','o.id')
                    ->whereIn(DB::raw('LOWER(TRIM(v.status))'), ['active','activo']);
            })
            // si filtran por placa no aplica aquí
            ->when($like !== null && $this->filter === 'name', fn($qq)=>$qq->where('o.name','like',$like))
            ->when($like !== null && $this->filter === 'code', fn($qq)=>$qq->where('o.id','like',$like))
            ->orderBy('o.name')
            ->select([
                'o.id','o.name','o.document_type','o.document_number',
                'o.document_expiration_date','o.birthdate','o.address','o.district',
                'o.email','o.phone'
            ]);

        $rows = $q->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r->id,
                (string)$r->name,
                strtoupper((string)$r->document_type),
                (string)$r->document_number, // texto
                !empty($r->document_expiration_date) ? Carbon::parse($r->document_expiration_date)->format('Y-m-d') : null,
                !empty($r->birthdate) ? Carbon::parse($r->birthdate)->format('Y-m-d') : null,
                (string)$r->address,
                (string)$r->district,
                (string)$r->email,
                (string)$r->phone, // texto
                '—', // placa
            ];
        }
        return $out;
    }

    private function compactWidths(Worksheet $ws): void
    {
        // A:ID B:Nombre C:TipoDoc D:NroDoc E:Venc F:Nac G:Dirección H:Distrito I:Email J:Tel K:Placa
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(26);
        $ws->getColumnDimension('C')->setWidth(10);
        $ws->getColumnDimension('D')->setWidth(16);
        $ws->getColumnDimension('E')->setWidth(12);
        $ws->getColumnDimension('F')->setWidth(12);
        $ws->getColumnDimension('G')->setWidth(28);
        $ws->getColumnDimension('H')->setWidth(16);
        $ws->getColumnDimension('I')->setWidth(26);
        $ws->getColumnDimension('J')->setWidth(14);
        $ws->getColumnDimension('K')->setWidth(12);
    }
}
