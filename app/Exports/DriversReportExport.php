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

    /** ====== Cabeceras comunes ====== */
    protected function headings(): array
    {
        return [
            'ID','Nombre','N° Documento','Teléfono','Email','Dirección','Distrito',
            'Licencia','Clase','Categoría',
            'F. Emisión Licencia','F. Revalidación Licencia',
            'Contrato Inicio','Contrato Fin',
            'Condición','Score',
            'Venc. Documento','Nacimiento',
            'Credencial','Venc. Credencial','Municipalidad Credencial',
            'Placas Activas',
        ];
    }

    /** ====== Data builder (dos tablas en una hoja) ====== */
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
                $d->name,
                $d->document_number,
                $d->phone,
                $d->email,
                $d->address,
                $d->district,
                $d->license,
                $d->class,
                $d->category,
                optional($d->license_issue_date)?->format('Y-m-d') ?: null,
                optional($d->license_revalidation_date)?->format('Y-m-d') ?: null,
                optional($d->contract_start)?->format('Y-m-d') ?: null,
                optional($d->contract_end)?->format('Y-m-d') ?: null,
                $d->condition,
                is_null($d->score) ? null : (float)$d->score,
                optional($d->document_expiration_date)?->format('Y-m-d') ?: null,
                optional($d->birthdate)?->format('Y-m-d') ?: null,
                $d->credential, // string o fecha según tu modelo
                optional($d->credential_expiration_date)?->format('Y-m-d') ?: null,
                $d->credential_municipality,
                $plates,
            ];
        }
        $rows[] = array_fill(0, count($head), ''); // fila TOTAL (se rellena en AfterSheet)

        // Separador
        $rows[] = array_fill(0, count($head), '');

        // Tabla 2: CONDUCTORES LIBRES
        $rows[] = $head;
        foreach ($free as $d) {
            $rows[] = [
                $d->id,
                $d->name,
                $d->document_number,
                $d->phone,
                $d->email,
                $d->address,
                $d->district,
                $d->license,
                $d->class,
                $d->category,
                optional($d->license_issue_date)?->format('Y-m-d') ?: null,
                optional($d->license_revalidation_date)?->format('Y-m-d') ?: null,
                optional($d->contract_start)?->format('Y-m-d') ?: null,
                optional($d->contract_end)?->format('Y-m-d') ?: null,
                $d->condition,
                is_null($d->score) ? null : (float)$d->score,
                optional($d->document_expiration_date)?->format('Y-m-d') ?: null,
                optional($d->birthdate)?->format('Y-m-d') ?: null,
                $d->credential,
                optional($d->credential_expiration_date)?->format('Y-m-d') ?: null,
                $d->credential_municipality,
                '', // sin placas activas por definición
            ];
        }
        $rows[] = array_fill(0, count($head), ''); // fila TOTAL (se rellena en AfterSheet)

        return $rows;
    }

    /** ====== Formatos de columnas (aplican a ambas tablas) ====== */
    public function columnFormats(): array
    {
        // A..V (22 columnas)
        return [
            'K' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Emisión Licencia
            'L' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Revalidación
            'M' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Contrato Inicio
            'N' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Contrato Fin
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Venc. Documento
            'R' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Nacimiento
            'T' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Venc. Credencial
            'P' => '0.00',                               // Score
        ];
    }

    /** ====== Eventos/estilos “línea de diseño Payments” ====== */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertar 2 filas para título y subtítulo
                $ws->insertNewRowBefore(1, 2);

                $lastCol = 'V'; // 22 columnas
                $head    = $this->headings();
                $colsCnt = count($head);

                // Calcular offsets y tamaños
                // Estructura actual (después de insertar 2 filas):
                // 3: encabezado T1
                // 4..(3+n1-1): datos T1
                // (3+n1): TOTAL T1
                // (3+n1+1): separador
                // (3+n1+2): encabezado T2
                // ... datos T2
                // TOTAL T2
                $rowsTotal = $ws->getHighestRow();

                // Contar filas por tabla a partir del contenido:
                // Tomamos n1 y n2 a partir de filas no vacías entre cabeceras
                $header1 = 3;
                // Busca header2: es la fila inmediatamente después del separador,
                // localízala detectando la primera fila que replica las cabeceras
                $header2 = null;
                for ($r = $header1 + 1; $r <= $rowsTotal; $r++) {
                    $valA = (string) $ws->getCell("A{$r}")->getValue();
                    if ($valA === 'ID') { $header2 = $r; break; }
                }
                if (!$header2) { $header2 = $rowsTotal; } // fallback

                $dataStart1 = $header1 + 1;
                $total1     = $header2 - 2; // (fila antes del separador)
                $n1         = max(0, $total1 - $dataStart1);

                $dataStart2 = $header2 + 1;
                $total2     = $rowsTotal; // última fila es TOTAL T2
                $n2         = max(0, $total2 - $dataStart2);

                // ===== TÍTULO (fila 1) y SUBTÍTULO (fila 2) =====
                $title = 'REPORTE DE CONDUCTORES';
                $ws->setCellValue('A1', $title);
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(1)->setRowHeight(24);

                $label = match ($this->filter) {
                    'plate' => 'Placa',
                    'name'  => 'Nombre',
                    'code'  => 'Código',
                    default => 'Búsqueda',
                };
                $sub = 'Filtros: ' . ($this->search ? "{$label}: {$this->search}" : '—');
                $ws->setCellValue('A2', $sub);
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $ws->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(2)->setRowHeight(18);

                // ===== Encabezados oscuros (header1 y header2) =====
                foreach ([$header1, $header2] as $hr) {
                    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")
                        ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")
                        ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getRowDimension($hr)->setRowHeight(20);
                    $ws->getStyle("A{$hr}:{$lastCol}{$hr}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F'); // #009BDC
                }

                // Freeze pane debajo del primer header
                $ws->freezePane("A" . ($header1 + 1));

                // Autofiltro SOLO para la primera tabla (Excel permite uno por hoja)
                if ($n1 > 0) {
                    $ws->setAutoFilter("A{$header1}:{$lastCol}" . ($total1 - 0)); // incluye datos T1
                } else {
                    $ws->setAutoFilter("A{$header1}:{$lastCol}{$header1}");
                }

                // Bordes finos (todo)
                $ws->getStyle("A{$header1}:{$lastCol}{$rowsTotal}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCFD8DC');

                // Zebra en ambos cuerpos
                $zebra = function (string $range) use ($ws) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF9FAFB');
                    $styles = $ws->getStyle($range)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($range)->setConditionalStyles($styles);
                };
                if ($n1 > 0) { $zebra("A{$dataStart1}:{$lastCol}" . ($total1 - 1)); }
                if ($n2 > 0) { $zebra("A{$dataStart2}:{$lastCol}" . ($total2 - 1)); }

                // Alineaciones útiles
                foreach (['D','P'] as $col) { // Teléfono, Score
                    $ws->getStyle("{$col}{$dataStart1}:{$col}{$total1}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("{$col}{$dataStart2}:{$col}{$total2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Anchos sugeridos extra
                $ws->getColumnDimension('A')->setWidth(8);
                $ws->getColumnDimension('B')->setWidth(24);
                $ws->getColumnDimension('C')->setWidth(16);
                $ws->getColumnDimension('D')->setWidth(14);
                $ws->getColumnDimension('E')->setWidth(26);
                $ws->getColumnDimension('F')->setWidth(28);
                $ws->getColumnDimension('G')->setWidth(18);
                $ws->getColumnDimension('H')->setWidth(14);
                $ws->getColumnDimension('I')->setWidth(10);
                $ws->getColumnDimension('J')->setWidth(12);
                $ws->getColumnDimension('V')->setWidth(26); // Placas activas

                // ===== Filas de TOTALES (una por tabla) =====
                // Total T1: merge A..U, conteo en V
                $ws->mergeCells("A{$total1}:U{$total1}");
                $ws->setCellValue("A{$total1}", 'TOTAL CONDUCTORES (con vehículo activo)');
                if ($n1 > 0) {
                    $ws->setCellValue("V{$total1}", "=COUNTA(A{$dataStart1}:A" . ($total1 - 1) . ")");
                } else {
                    $ws->setCellValue("V{$total1}", 0);
                }
                // Total T2
                $ws->mergeCells("A{$total2}:U{$total2}");
                $ws->setCellValue("A{$total2}", 'TOTAL CONDUCTORES LIBRES');
                if ($n2 > 0) {
                    $ws->setCellValue("V{$total2}", "=COUNTA(A{$dataStart2}:A" . ($total2 - 1) . ")");
                } else {
                    $ws->setCellValue("V{$total2}", 0);
                }

                // Estilo de pies = thead oscuro
                foreach ([$total1, $total2] as $tr) {
                    $ws->getStyle("A{$tr}:{$lastCol}{$tr}")
                        ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                    $ws->getStyle("A{$tr}:{$lastCol}{$tr}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                    $ws->getStyle("A{$tr}:U{$tr}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("V{$tr}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $ws->getStyle("A{$tr}:{$lastCol}{$tr}")
                        ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                }
            },
        ];
    }

    /** ====== Helpers: query de datos ====== */
    protected function fetchData(): array
    {
        $statuses = ['active','activo'];
        $filter   = (string) $this->filter;
        $search   = trim((string) $this->search);

        // Conductores con vehículo ACTIVO
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
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate', 'like', "%{$search}%")),
                    'name'  => $q->where('name', 'like', "%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->whereHas('vehicles', fn($qq) => $qq->where('id', (int)$search))
                        : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->get([
                'id','name','document_number','phone','email','address','district',
                'license','class','category',
                'license_issue_date','license_revalidation_date',
                'contract_start','contract_end',
                'condition','score',
                'document_expiration_date','birthdate',
                'credential','credential_expiration_date','credential_municipality',
            ]);

        // Conductores LIBRES (sin vehículo activo)
        $free = Driver::query()
            ->whereDoesntHave('vehicles', fn($q) =>
            $q->whereIn(DB::raw("LOWER(TRIM(status))"), $statuses)
            )
            ->when($filter && $search !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    // permitimos buscar por placa/código en cualquier vehículo histórico
                    'plate' => $q->whereHas('vehicles', fn($qq) => $qq->where('plate', 'like', "%{$search}%")),
                    'name'  => $q->where('name', 'like', "%{$search}%"),
                    'code'  => ctype_digit($search)
                        ? $q->whereHas('vehicles', fn($qq) => $qq->where('id', (int)$search))
                        : $q,
                    default => $q,
                };
            })
            ->orderBy('name')
            ->get([
                'id','name','document_number','phone','email','address','district',
                'license','class','category',
                'license_issue_date','license_revalidation_date',
                'contract_start','contract_end',
                'condition','score',
                'document_expiration_date','birthdate',
                'credential','credential_expiration_date','credential_municipality',
            ]);

        return [$active, $free];
    }
}
