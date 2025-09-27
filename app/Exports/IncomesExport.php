<?php

namespace App\Exports;

use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

class IncomesExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected ?string $search = '',
        protected int $filterType = 1,   // 1=reason, 2=detail, 3=user.name
        protected ?string $date_start = null,
        protected ?string $date_end = null
    ) {}

    /** ========================= QUERY ========================= */
    public function query(): Builder
    {
        $q = Income::query()
            ->with(['user:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        // Fechas
        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date', [$this->date_start, $this->date_end]);
        } elseif ($this->date_start) {
            $q->where('date', '>=', $this->date_start);
        } elseif ($this->date_end) {
            $q->where('date', '<=', $this->date_end);
        }

        // Búsqueda
        $s = trim((string)$this->search);
        if ($s !== '') {
            switch ((int)$this->filterType) {
                case 1: $q->where('reason', 'like', "%{$s}%"); break;
                case 2: $q->where('detail', 'like', "%{$s}%"); break;
                case 3: $q->whereHas('user', fn($qq) => $qq->where('name', 'like', "%{$s}%")); break;
                default: $q->where('reason', 'like', "%{$s}%");
            }
        }

        return $q->select(['id','date','reason','detail','total','user_id','created_at']);
    }

    /** ========================= LAYOUT / MAPPING ========================= */
    public function headings(): array
    {
        return ['ID','Fecha','Motivo','Detalle','Total','Usuario','Creado'];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->date ? Carbon::parse($row->date) : null,                   // Fecha Excel
            $row->reason,
            $row->detail,
            is_null($row->total) ? null : (float)$row->total,                // Número
            optional($row->user)->name,
            $row->created_at ? Carbon::parse($row->created_at) : null,       // Fecha/Hora Excel
        ];
    }

    public function columnFormats(): array
    {
        // A B C D E F G
        return [
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Fecha
            'G' => NumberFormat::FORMAT_DATE_DATETIME,  // Creado
            // 'E' moneda se setea abajo en AfterSheet
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

                // Paleta final
                $PRIMARY   = 'FF212529';   // #212529 (ARGB)
                $PRIMARYLT = 'FFEDEEEF';   // gris suave p/ bloque de títulos
                $WHITE     = 'FFFFFFFF';
                $BORDER    = 'FFCFD8DC';
                $ZEBRA     = 'FFF9FAFB';

                // Insertar 2 filas para Título (1) y Subtítulo (2)
                $ws->insertNewRowBefore(1, 2);

                // Título (centrado)
                $ws->setCellValue('A1', 'Reporte de Ingresos');
                $ws->mergeCells('A1:G1');
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $ws->getStyle('A1')->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Subtítulo (centrado)
                $rangeText = ($this->date_start ?: '—') . ' a ' . ($this->date_end ?: '—');
                $label = match ((int)$this->filterType) {
                    1 => 'Motivo',
                    2 => 'Detalle',
                    3 => 'Usuario',
                    default => 'Búsqueda',
                };
                $filters = 'Rango: ' . $rangeText;
                if (trim((string)$this->search) !== '') {
                    $filters .= ' | ' . $label . ': ' . $this->search;
                }
                $ws->setCellValue('A2', $filters);
                $ws->mergeCells('A2:G2');
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
                $ws->getStyle('A2')->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);

                // Fondo suave al bloque de títulos
                $ws->getStyle('A1:G2')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($PRIMARYLT);

                // Fila de encabezados y de datos
                $headerRow    = 3;  // fila del encabezado (headings)
                $dataStartRow = 4;  // primera fila de datos
                $last         = $ws->getHighestRow();

                // THEAD (bg #212529 + texto blanco, centrado)
                $ws->getStyle("A{$headerRow}:G{$headerRow}")->getFont()
                    ->setBold(true)->getColor()->setARGB($WHITE);
                $ws->getStyle("A{$headerRow}:G{$headerRow}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);
                $ws->getStyle("A{$headerRow}:G{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($PRIMARY);
                $ws->getRowDimension($headerRow)->setRowHeight(22);

                // Anchos mínimos (setWidth prevalece sobre ShouldAutoSize)
                $ws->getColumnDimension('A')->setWidth(10); // ID
                $ws->getColumnDimension('B')->setWidth(12); // Fecha
                $ws->getColumnDimension('C')->setWidth(22); // Motivo
                $ws->getColumnDimension('D')->setWidth(44); // Detalle
                $ws->getColumnDimension('E')->setWidth(16); // Total
                $ws->getColumnDimension('F')->setWidth(24); // Usuario
                $ws->getColumnDimension('G')->setWidth(20); // Creado

                // Wrap en columnas de texto largas
                $ws->getStyle("C{$dataStartRow}:D" . max($dataStartRow, $last))
                    ->getAlignment()->setWrapText(true);

                // Congelar por debajo del header
                $ws->freezePane("A{$dataStartRow}");

                // Si no hay datos, al menos deja el autofiltro en el header y sal
                if ($last < $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:G{$headerRow}");
                    return;
                }

                // Autofiltro
                $ws->setAutoFilter("A{$headerRow}:G{$last}");

                // Zebra stripes (filas pares)
                $cond = new Conditional();
                $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                $cond->setConditions(['MOD(ROW(),2)=0']);
                $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($ZEBRA);
                $rangeData = "A{$dataStartRow}:G{$last}";
                $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($rangeData)->setConditionalStyles($styles);

                // Bordes finos para header + datos
                $ws->getStyle("A{$headerRow}:G{$last}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);

                // Alineación: números a la derecha en Total
                $ws->getStyle("E{$dataStartRow}:E{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Formatos (separados de la alineación para evitar el error)
                $ws->getStyle("B{$dataStartRow}:B{$last}")
                    ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $ws->getStyle("G{$dataStartRow}:G{$last}")
                    ->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm');
                $ws->getStyle("E{$dataStartRow}:E{$last}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                // Fila TOTAL (mismo fondo que thead: #212529; texto blanco)
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:D{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                $ws->setCellValue("E{$totalRow}", "=SUM(E{$dataStartRow}:E{$last})");

                $ws->getStyle("A{$totalRow}:G{$totalRow}")->getFont()
                    ->setBold(true)->getColor()->setARGB($WHITE);
                // Etiqueta TOTAL a la derecha, monto a la derecha
                $ws->getStyle("A{$totalRow}:D{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("E{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                // Formato moneda (separado de la alineación para no encadenar)
                $ws->getStyle("E{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                // Mismo color de fondo que el thead
                $ws->getStyle("A{$totalRow}:G{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($PRIMARY);

                // Borde superior marcado
                $ws->getStyle("A{$totalRow}:G{$totalRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setARGB($BORDER);
            },
        ];
    }
}
