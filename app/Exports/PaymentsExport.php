<?php

namespace App\Exports;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class PaymentsExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected string  $search         = '',
        protected string  $filter         = '',          // '1' placa, '2' usuario, '3' serie, '' mixto
        protected ?string $date_start     = null,        // YYYY-MM-DD
        protected ?string $date_end       = null,        // YYYY-MM-DD
        protected ?string $headquarter_id = '',          // '' = todos
        protected ?string $type           = ''           // '' = todos
    ) {}

    /** ========================= QUERY ========================= */
    public function query(): Builder
    {
        $q = Payment::query()
            ->with([
                'vehicle:id,plate',
                'user:id,name',
                'headquarter:id,name',
            ]);

        // Rango de fechas por date_register (fallback a HOY si viene vacío)
        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date_register', [$this->date_start, $this->date_end]);
        } else {
            $today = now()->toDateString();
            $q->whereBetween('date_register', [$this->date_start ?: $today, $this->date_end ?: $today]);
        }

        // Sucursal exacta (opcional)
        if ($this->headquarter_id !== '' && $this->headquarter_id !== null) {
            $q->where('headquarter_id', $this->headquarter_id);
        }

        // Tipo exacto (opcional)
        if ($this->type !== '' && $this->type !== null) {
            $q->where('type', $this->type);
        }

        // Búsqueda según filtro seleccionado
        $term = trim($this->search);
        if ($term !== '') {
            switch ($this->filter) {
                case '1': // placa (legacy o relación)
                    $plate = strtoupper($term);
                    $q->where(function ($qq) use ($plate) {
                        $qq->where('legacy_plate', 'like', "%{$plate}%")
                            ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', "%{$plate}%"));
                    });
                    break;

                case '2': // usuario
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%"));
                    break;

                case '3': // serie
                    $q->where('serie', 'like', "%{$term}%");
                    break;

                default:  // mixto
                    $plate = strtoupper($term);
                    $q->where(function ($qq) use ($term, $plate) {
                        $qq->where('serie', 'like', "%{$term}%")
                            ->orWhere('legacy_plate', 'like', "%{$plate}%")
                            ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', "%{$plate}%"))
                            ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%"));
                    });
                    break;
            }
        }

        // ==== Selección de columnas con auto-detección del importe ====
        $table      = (new Payment)->getTable();
        $cols       = Schema::getColumnListing($table);
        $candidates = ['amount','total','total_amount','importe','import','price','value','amount_total'];
        $amountCol  = collect($candidates)->first(fn($c) => in_array($c, $cols, true));

        $select = [
            'id',
            'date_register',
            'hour',
            'serie',
            'type',
            'headquarter_id',
            'vehicle_id',
            'user_id',
            'legacy_plate',
            'created_at',
        ];

        if ($amountCol) {
            // payments.amountCol AS amount_calc
            $select[] = DB::raw("$table.$amountCol as amount_calc");
        }

        return $q->orderBy('date_register')
            ->orderBy('hour')
            ->select($select);
    }

    /** ========================= LAYOUT / MAPPING ========================= */
    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Hora',
            'Serie',
            'Tipo',
            'Sucursal',
            'Placa',
            'Usuario',
            'Importe',
            'Creado',
        ];
    }

    public function map($row): array
    {
        $plate  = $row->legacy_plate ?: optional($row->vehicle)->plate;
        $amount = $row->amount_calc ?? null; // si no se detectó, vendrá null

        return [
            $row->id,
            $row->date_register ? Carbon::parse($row->date_register) : null, // fecha Excel
            $row->hour,                                                      // HH:MM:SS
            $row->serie,
            $row->type,
            optional($row->headquarter)->name,
            $plate,
            optional($row->user)->name,
            is_null($amount) ? null : (float) $amount,
            $row->created_at ? Carbon::parse($row->created_at) : null,       // fecha/hora Excel
        ];
    }

    public function columnFormats(): array
    {
        // A B C D E F G H I J
        return [
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Fecha
            'C' => NumberFormat::FORMAT_DATE_TIME3,     // Hora
            // 'I' currency custom lo aplicamos en AfterSheet para usar S/
            'J' => NumberFormat::FORMAT_DATE_DATETIME,  // Creado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // El header (que moveremos a la fila 3) va bold; reforzado en AfterSheet
        return [1 => ['font' => ['bold' => true]]];
    }

    /** ========================= ESTILOS AVANZADOS ========================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertar filas arriba para Título (fila 1) y Subtítulo (fila 2)
                $ws->insertNewRowBefore(1, 2);

                // Título
                $title = 'Reporte de Pagos';
                $ws->setCellValue('A1', $title);
                $ws->mergeCells('A1:J1');
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Subtítulo con filtros
                $rangeText = ($this->date_start ?: '—') . ' a ' . ($this->date_end ?: '—');
                $filters = [];
                if ($this->headquarter_id !== '' && $this->headquarter_id !== null) $filters[] = "Sucursal: {$this->headquarter_id}";
                if ($this->type !== '' && $this->type !== null)                     $filters[] = "Tipo: {$this->type}";
                if (trim($this->search) !== '') {
                    $label = match ($this->filter) {
                        '1' => 'Placa',
                        '2' => 'Usuario',
                        '3' => 'Serie',
                        default => 'Búsqueda',
                    };
                    $filters[] = "{$label}: " . $this->search;
                }
                $subtitle = 'Rango: ' . $rangeText . (count($filters) ? ' | ' . implode(' · ', $filters) : '');
                $ws->setCellValue('A2', $subtitle);
                $ws->mergeCells('A2:J2');
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
                $ws->getStyle('A2')->getAlignment()->setWrapText(true);

                // Ubicación de encabezado y datos tras insertar 2 filas:
                $headerRow    = 3;  // headings()
                $dataStartRow = 4;
                $last         = $ws->getHighestRow();

                // Estilo de encabezado
                $ws->getStyle("A{$headerRow}:J{$headerRow}")->getFont()->setBold(true);
                $ws->getStyle("A{$headerRow}:J{$headerRow}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);
                $ws->getRowDimension($headerRow)->setRowHeight(20);
                $ws->getStyle("A{$headerRow}:J{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE5E7EB'); // gris claro

                // Anchos de columna (además de autosize)
                $ws->getColumnDimension('A')->setWidth(8);
                $ws->getColumnDimension('B')->setWidth(12);
                $ws->getColumnDimension('C')->setWidth(10);
                $ws->getColumnDimension('D')->setWidth(14);
                $ws->getColumnDimension('E')->setWidth(12);
                $ws->getColumnDimension('F')->setWidth(22);
                $ws->getColumnDimension('G')->setWidth(12);
                $ws->getColumnDimension('H')->setWidth(22);
                $ws->getColumnDimension('I')->setWidth(14);
                $ws->getColumnDimension('J')->setWidth(18);

                // Congelar por debajo del header
                $ws->freezePane("A{$dataStartRow}");

                // Si no hay filas de datos, aún aplicar autofiltro al header y salir elegante
                if ($last < $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:J{$headerRow}");
                    return;
                }

                // Autofiltro
                $ws->setAutoFilter("A{$headerRow}:J{$last}");

                // Zebra stripes mediante formato condicional (sobre el rango de datos)
                $cond = new Conditional();
                $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                $cond->setConditions(['MOD(ROW(),2)=0']);
                $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF9FAFB'); // gris muy suave
                $rangeData = "A{$dataStartRow}:J{$last}";
                $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($rangeData)->setConditionalStyles($styles);

                // Bordes finos para todo (header + datos)
                $ws->getStyle("A{$headerRow}:J{$last}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCFD8DC');

                // Alineación: números a la derecha
                $ws->getStyle("I{$dataStartRow}:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Formato moneda S/ para Importe (col I) + formato fecha/hora (ya dado en columnFormats, pero reforzamos rango de datos)
                $ws->getStyle("I{$dataStartRow}:I{$last}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                // Fila de totales
                $totalRow = $last + 1;
                // Merge para etiqueta TOTAL
                $ws->mergeCells("A{$totalRow}:H{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                $ws->setCellValue("I{$totalRow}", "=SUM(I{$dataStartRow}:I{$last})");

                // Estilo de la fila de totales
                $ws->getStyle("A{$totalRow}:J{$totalRow}")->getFont()->setBold(true);
                $ws->getStyle("A{$totalRow}:J{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF3F4F6'); // gris clarito
                $ws->getStyle("A{$totalRow}:J{$totalRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $ws->getStyle("I{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                $ws->getStyle("A{$totalRow}:H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Ajuste final por si el archivo se abre en otra cultura
                $ws->getStyle("B{$dataStartRow}:B{$last}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $ws->getStyle("C{$dataStartRow}:C{$last}")->getNumberFormat()->setFormatCode('hh:mm:ss');
                $ws->getStyle("J{$dataStartRow}:J{$last}")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
            },
        ];
    }
}
