<?php

namespace App\Exports;

use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class DriversReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    public function __construct(
        protected ?string $search = null,
        protected ?string $filter = 'plate' // plate | name | code
    ) {}

    protected function headings(): array
    {
        // A..H
        return [
            'ID', 'Placa', 'Nombre', 'N° Documento',
            'Contrato Inicio', 'Contrato Fin', 'Teléfono', 'Condición',
        ];
    }

    public function array(): array
    {
        [$active, $free] = $this->fetchData();

        $head = $this->headings();
        $rows = [];

        // Tabla 1: CON VEHÍCULO ACTIVO
        $rows[] = $head;
        foreach ($active as $d) {
            $plates = $d->relationLoaded('vehicles')
                ? $d->vehicles->pluck('plate')->filter()->unique()->values()->implode(', ')
                : '';

            $rows[] = [
                $d->id,
                $plates ?: '',
                (string)$d->name,
                (string)$d->document_number,
                optional($d->contract_start)?->format('Y-m-d') ?: null,
                optional($d->contract_end)?->format('Y-m-d') ?: null,
                (string)$d->phone,
                (string)$d->condition,
            ];
        }
        $rows[] = array_fill(0, count($head), ''); // TOTAL T1

        // Separador
        $rows[] = array_fill(0, count($head), '');

        // Tabla 2: CONDUCTORES LIBRES
        $rows[] = $head;
        foreach ($free as $d) {
            $rows[] = [
                $d->id,
                '—',
                (string)$d->name,
                (string)$d->document_number,
                optional($d->contract_start)?->format('Y-m-d') ?: null,
                optional($d->contract_end)?->format('Y-m-d') ?: null,
                (string)$d->phone,
                (string)$d->condition,
            ];
        }
        $rows[] = array_fill(0, count($head), ''); // TOTAL T2

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'D' => '@',                                 // N° Documento texto
            'E' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Contrato Inicio
            'F' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Contrato Fin
            'G' => '@',                                 // Teléfono texto
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // ======= Paleta =======
                $blue     = 'FF2874A6'; // #2874A6
                $footerBg = 'FFCEE7FF'; // #CEE7FF
                $white    = 'FFFFFFFF';
                $borderC  = 'FFCFD8DC';

                // Tamaño de fuente global = 10 (no se toca)
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Insertar 2 filas para título y (sin subtítulo)
                $ws->insertNewRowBefore(1, 2);
                $lastCol = 'H';

                // ======= Título (fila 1) =======
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', 'REPORTE DE CONDUCTORES');
                $ws->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(16);

                // ======= Fila 2 vacía (sin “Filtros”), solo como separador fino =======
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->setCellValue('A2', '');
                // mantenemos una línea azul muy delgada para continuidad visual
                $ws->getStyle('A2')->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                ]);
                $ws->getRowDimension(2)->setRowHeight(6);

                // Detectar bloques
                $header1    = 3;
                $dataStart1 = 4;
                $rowsTotal  = (int)$ws->getHighestRow();
                $header2    = null;
                for ($r = $dataStart1; $r <= $rowsTotal; $r++) {
                    if ((string)$ws->getCell("A{$r}")->getValue() === 'ID') { $header2 = $r; break; }
                }
                if (!$header2) { $header2 = $rowsTotal; }
                $total1     = $header2 - 2;
                $dataEnd1   = max($dataStart1, $total1 - 1);
                $dataStart2 = $header2 + 1;
                $total2     = $rowsTotal;
                $dataEnd2   = max($dataStart2, $total2 - 1);

                // ======= Encabezados (ambas tablas) =======
                foreach ([$header1, $header2] as $hr) {
                    if ($hr < 3) continue;
                    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")->applyFromArray([
                        'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$white]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$blue]],
                    ]);
                    $ws->getRowDimension($hr)->setRowHeight(15);
                }

                // Freeze
                $ws->freezePane('A4');

                // Bordes finos
                $ws->getStyle("A{$header1}:{$lastCol}{$total2}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($borderC);

                // Zebra
                $zebra = function (string $range) use ($ws) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF3F4F6');
                    $styles = $ws->getStyle($range)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($range)->setConditionalStyles($styles);
                };
                if ($dataEnd1 >= $dataStart1) $zebra("A{$dataStart1}:{$lastCol}{$dataEnd1}");
                if ($dataEnd2 >= $dataStart2) $zebra("A{$dataStart2}:{$lastCol}{$dataEnd2}");

                // Alineaciones + shrink
                foreach ([[$dataStart1,$dataEnd1],[$dataStart2,$dataEnd2]] as [$r1,$r2]) {
                    if ($r2 < $r1) continue;
                    $ws->getStyle("A{$r1}:A{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ID
                    $ws->getStyle("B{$r1}:B{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Placa
                    $ws->getStyle("C{$r1}:C{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true); // Nombre
                    $ws->getStyle("D{$r1}:D{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true); // Doc
                    $ws->getStyle("E{$r1}:F{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Fechas
                    $ws->getStyle("G{$r1}:G{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setShrinkToFit(true); // Tel
                    $ws->getStyle("H{$r1}:H{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Cond
                }

                // ======= Footers (mismo esquema, fondo #CEE7FF) =======
                // Total T1
                $ws->mergeCells("A{$total1}:G{$total1}");
                $ws->setCellValue("A{$total1}", 'TOTAL CONDUCTORES (con vehículo activo)');
                $ws->setCellValue("H{$total1}", $dataEnd1 >= $dataStart1 ? "=COUNTA(A{$dataStart1}:A{$dataEnd1})" : 0);
                $ws->getStyle("A{$total1}:{$lastCol}{$total1}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>'FF000000']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                ]);
                $ws->getStyle("H{$total1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Total T2
                $ws->mergeCells("A{$total2}:G{$total2}");
                $ws->setCellValue("A{$total2}", 'TOTAL CONDUCTORES LIBRES');
                $ws->setCellValue("H{$total2}", $dataEnd2 >= $dataStart2 ? "=COUNTA(A{$dataStart2}:A{$dataEnd2})" : 0);
                $ws->getStyle("A{$total2}:{$lastCol}{$total2}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>'FF000000']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$footerBg]],
                    'borders' => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['argb'=>$blue]]],
                ]);
                $ws->getStyle("H{$total2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ======= Anchos AÚN MÁS COMPACTOS (sin cambiar la letra) =======
                // A:ID, B:Placa, C:Nombre, D:Doc, E:Ini, F:Fin, G:Tel, H:Cond
                $ws->getColumnDimension('A')->setWidth(5.0);
                $ws->getColumnDimension('B')->setWidth(9.5);
                $ws->getColumnDimension('C')->setWidth(13.0); // bajado (más compacto)
                $ws->getColumnDimension('D')->setWidth(12.0); // bajado
                $ws->getColumnDimension('E')->setWidth(9.0);  // bajado
                $ws->getColumnDimension('F')->setWidth(9.0);  // bajado
                $ws->getColumnDimension('G')->setWidth(10.0); // bajado
                $ws->getColumnDimension('H')->setWidth(8.5);  // bajado
            },
        ];
    }

    protected function fetchData(): array
    {
        $statuses = ['active','activo'];
        $filter   = (string) $this->filter;
        $search   = trim((string) $this->search);

        $active = Driver::query()
            ->whereHas('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->with(['vehicles' => fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
                ->select('id','driver_id','plate','status')
            ])
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->whereHas('vehicles', fn($qq) => $qq->where('id',(int)$search))
                        : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->get(['id','name','document_number','phone','condition','contract_start','contract_end']);

        $free = Driver::query()
            ->whereDoesntHave('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->whereHas('vehicles', fn($qq) => $qq->where('id',(int)$search))
                        : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->get(['id','name','document_number','phone','condition','contract_start','contract_end']);

        return [$active, $free];
    }
}
