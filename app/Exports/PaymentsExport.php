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

        // Rango por date_register (si no viene, usar HOY)
        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date_register', [$this->date_start, $this->date_end]);
        } else {
            $today = now()->toDateString();
            $q->whereBetween('date_register', [$this->date_start ?: $today, $this->date_end ?: $today]);
        }

        // Sucursal (opcional)
        if ($this->headquarter_id !== '' && $this->headquarter_id !== null) {
            $q->where('headquarter_id', $this->headquarter_id);
        }

        // Tipo (opcional)
        if ($this->type !== '' && $this->type !== null) {
            $q->where('type', $this->type);
        }

        // Búsqueda según filtro
        $term = trim($this->search);
        if ($term !== '') {
            switch ($this->filter) {
                case '1': // placa
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
        $amount = $row->amount_calc ?? null;

        return [
            $row->id,
            $row->date_register ? Carbon::parse($row->date_register) : null, // fecha
            $row->hour,                                                      // HH:MM:SS
            $row->serie,
            $row->type,
            optional($row->headquarter)->name,
            $plate,
            optional($row->user)->name,
            is_null($amount) ? null : (float) $amount,
            $row->created_at ? Carbon::parse($row->created_at) : null,       // fecha/hora
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Fecha
            'C' => NumberFormat::FORMAT_DATE_TIME3,     // Hora
            'J' => NumberFormat::FORMAT_DATE_DATETIME,  // Creado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // El header quedará en la fila 3 (negrita), se refuerza en AfterSheet
        return [1 => ['font' => ['bold' => true]]];
    }

    /** ========================= ESTILOS “HOMOLOGADOS” ========================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertamos 2 filas para Título y Subtítulo
                $ws->insertNewRowBefore(1, 2);

                // ===== Título (fila 1) =====
                $title = 'REPORTE DE PAGOS';
                $ws->setCellValue('A1', $title);
                $ws->mergeCells('A1:J1');
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $ws->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937'); // título oscuro
                $ws->getRowDimension(1)->setRowHeight(24);

                // ===== Subtítulo (fila 2): filtros =====
                $rangeText = ($this->date_start ?: '—') . ' a ' . ($this->date_end ?: '—');

                $filters = [];
                // Sucursal por nombre si existe
                if ($this->headquarter_id !== '' && $this->headquarter_id !== null) {
                    $hqName = null;
                    if (Schema::hasTable('headquarters')) {
                        $hqName = DB::table('headquarters')->where('id', $this->headquarter_id)->value('name');
                    }
                    $filters[] = 'Sucursal: ' . ($hqName ?: $this->headquarter_id);
                }
                if ($this->type !== '' && $this->type !== null) {
                    $filters[] = "Tipo: {$this->type}";
                }
                if (trim($this->search) !== '') {
                    $label = match ($this->filter) {
                        '1' => 'Placa',
                        '2' => 'Usuario',
                        '3' => 'Serie',
                        default => 'Búsqueda',
                    };
                    $filters[] = "{$label}: {$this->search}";
                }

                $subtitle = 'Rango: ' . $rangeText . (count($filters) ? ' | ' . implode(' · ', $filters) : '');
                $ws->setCellValue('A2', $subtitle);
                $ws->mergeCells('A2:J2');
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle('A2')
                    ->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $ws->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
                $ws->getRowDimension(2)->setRowHeight(18);

                // ===== Header en fila 3 (oscuro) =====
                $headerRow    = 3;
                $dataStartRow = 4;
                $last         = (int)$ws->getHighestRow();

                $ws->getStyle("A{$headerRow}:J{$headerRow}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$headerRow}:J{$headerRow}")
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $ws->getRowDimension($headerRow)->setRowHeight(20);
                $ws->getStyle("A{$headerRow}:J{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F'); // thead #009BDC

                // Congelar encabezado
                $ws->freezePane("A{$dataStartRow}");

                // Si no hay datos, aplicar autofiltro igual y salir bonito
                if ($last < $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:J{$headerRow}");
                    return;
                }

                // Autofiltro sobre datos
                $ws->setAutoFilter("A{$headerRow}:J{$last}");

                // Bordes finos (header + datos)
                $ws->getStyle("A{$headerRow}:J{$last}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCFD8DC');

                // Zebra en cuerpo (gris muy suave)
                $rangeData = "A{$dataStartRow}:J{$last}";
                $cond = new Conditional();
                $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                $cond->setConditions(['MOD(ROW(),2)=0']);
                $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF9FAFB');
                $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($rangeData)->setConditionalStyles($styles);

                // Alineaciones y formatos
                $ws->getStyle("I{$dataStartRow}:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("I{$dataStartRow}:I{$last}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                // Reforzar formatos de fecha/hora por rango
                $ws->getStyle("B{$dataStartRow}:B{$last}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $ws->getStyle("C{$dataStartRow}:C{$last}")->getNumberFormat()->setFormatCode('hh:mm:ss');
                $ws->getStyle("J{$dataStartRow}:J{$last}")->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');

                // Anchos sugeridos (además de autosize)
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

                // ===== Fila de totales (pie oscuro como thead) =====
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:H{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                $ws->setCellValue("I{$totalRow}", "=SUM(I{$dataStartRow}:I{$last})");

                // Estilo pie = #009BDC, texto blanco
                $ws->getStyle("A{$totalRow}:J{$totalRow}")
                    ->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $ws->getStyle("A{$totalRow}:J{$totalRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF23242F');
                $ws->getStyle("A{$totalRow}:J{$totalRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $ws->getStyle("A{$totalRow}:H{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("I{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
            },
        ];
    }
}
