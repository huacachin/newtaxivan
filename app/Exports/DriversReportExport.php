<?php

namespace App\Exports;

use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class DriversReportExport implements FromArray, WithColumnFormatting, WithEvents, WithColumnWidths
{
    public function __construct(
        protected ?string $search = null,
        protected ?string $filter = 'plate' // plate | name | code
    ) {}

    /** Correlativos por tabla */
    private int $rowNumActive = 0;
    private int $rowNumFree   = 0;

    /** Totales para mostrar en el título */
    private int $totalActive  = 0;
    private int $totalFree    = 0;

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

        // Guardamos totales para el título
        $this->totalActive = $active->count();
        $this->totalFree   = $free->count();

        $head = $this->headings();
        $rows = [];

        // ===== Tabla 1: CON VEHÍCULO ACTIVO =====
        $rows[] = $head;
        foreach ($active as $d) {
            // Placas ordenadas por sort_order (NULL al final), luego plate
            $plates = $d->relationLoaded('vehicles')
                ? $d->vehicles
                    ->sortBy([
                        fn($a, $b) => ($a->sort_order === null ? 1 : 0) <=> ($b->sort_order === null ? 1 : 0),
                        ['sort_order', 'asc'],
                        ['plate', 'asc'],
                    ])
                    ->pluck('plate')->filter()->unique()->values()->implode(', ')
                : '';

            $rows[] = [
                ++$this->rowNumActive,                   // ID correlativo
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

        // ===== Tabla 2: CONDUCTORES LIBRES =====
        $rows[] = $head;
        foreach ($free as $d) {
            $rows[] = [
                ++$this->rowNumFree,                     // ID correlativo (tabla 2)
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

    public function columnWidths(): array
    {
        // Anchos “al ras” para todas excepto B y C (que se autoajustan en AfterSheet)
        return [
            'A' => 4.2,   // ID
            // 'B' => (AutoSize)
            // 'C' => (AutoSize)
            'D' => 12.0,  // N° Documento
            'E' => 9.0,   // Contrato Inicio
            'F' => 9.0,   // Contrato Fin
            'G' => 11.0,  // Teléfono
            'H' => 7.0,   // Condición
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
                $black = '000000';

                // Tamaño de fuente global = 10
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Insertar 2 filas para título y (sin subtítulo)
                $ws->insertNewRowBefore(1, 2);
                $lastCol = 'H';

                // ======= Título (fila 1) con TOTAL =======
                $total = $this->totalActive + $this->totalFree;
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', "REPORTE DE CONDUCTORES (Total: {$total})");
                $ws->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'color'=>['argb'=>$black]],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(16);

                // ======= Fila 2 vacía (separador fino) =======
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->setCellValue('A2', '');
                $ws->getStyle('A2')->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$white]],
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

                // ======= Alineaciones =======
                foreach ([[$dataStart1,$dataEnd1],[$dataStart2,$dataEnd2]] as [$r1,$r2]) {
                    if ($r2 < $r1) continue;
                    $ws->getStyle("A{$r1}:A{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ID

                    // B: Placa — SIN Shrink, SIN wrap (AutoSize)
                    $ws->getStyle("B{$r1}:B{$r2}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setWrapText(false);

                    // C: Nombre — SIN Shrink, SIN wrap (AutoSize)
                    $ws->getStyle("C{$r1}:C{$r2}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(false);

                    // D: Documento — compacto con Shrink (si no lo quieres, quita setShrinkToFit)
                    $ws->getStyle("D{$r1}:D{$r2}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setShrinkToFit(true)
                        ->setWrapText(false);

                    // E-F: Fechas
                    $ws->getStyle("E{$r1}:F{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // G: Teléfono — compacto con Shrink
                    $ws->getStyle("G{$r1}:G{$r2}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setShrinkToFit(true)
                        ->setWrapText(false);

                    // H: Condición
                    $ws->getStyle("H{$r1}:H{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // ======= Anchos =======
                // Fijos “al ras” para todas menos B y C:
                $ws->getColumnDimension('A')->setWidth(4.2);
                // B y C: AutoSize para respetar fuente 10 sin reducir tamaño
                $ws->getColumnDimension('B')->setAutoSize(true);
                $ws->getColumnDimension('C')->setAutoSize(true);
                $ws->getColumnDimension('D')->setWidth(12.0);
                $ws->getColumnDimension('E')->setWidth(9.0);
                $ws->getColumnDimension('F')->setWidth(9.0);
                $ws->getColumnDimension('G')->setWidth(11.0);
                $ws->getColumnDimension('H')->setWidth(7.0);

                // (Opcional) Si tu entorno no recalcula el autosize inmediatamente:
                // $ws->calculateColumnWidths();
            },
        ];
    }

    /**
     * Trae:
     *  - $active: conductores con al menos un vehículo 'active/activo', con atributo virtual sort_order_min
     *             y relación vehicles ya ordenada por sort_order (NULL al final) y luego plate.
     *  - $free:   conductores sin vehículos activos.
     */
    protected function fetchData(): array
    {
        $statuses = ['active','activo'];
        $filter   = (string) $this->filter;
        $search   = trim((string) $this->search);

        // === Activos: orden por vehicles.sort_order (NULL al final), después por name ===
        $active = Driver::query()
            ->whereHas('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->withMin(['vehicles as sort_order_min' => function ($q) use ($statuses) {
                $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses);
            }], 'sort_order')
            ->with(['vehicles' => function ($q) use ($statuses) {
                $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
                    ->select('id','driver_id','plate','status','sort_order')
                    ->orderByRaw('sort_order IS NULL, sort_order ASC')
                    ->orderBy('plate');
            }])
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->where('id', (int)$search)
                        : $q,
                    default => $q,
                };
            })
            ->orderByRaw('sort_order_min IS NULL, sort_order_min ASC')
            ->orderBy('name')
            ->get(['id','name','document_number','phone','condition','contract_start','contract_end']);

        // === Libres: no tienen vehículos activos ===
        $free = Driver::query()
            ->whereDoesntHave('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate','like',"%{$search}%")),
                    'name'  => $q->where('name','like',"%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->where('id', (int)$search)
                        : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->get(['id','name','document_number','phone','condition','contract_start','contract_end']);

        return [$active, $free];
    }
}
