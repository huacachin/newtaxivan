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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class IncomesExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    /** contador de item (1..n) */
    private int $i = 0;

    public function __construct(
        protected ?string $search = '',
        protected int $filterType = 1,   // 1=reason, 2=detail, 3=user.name
        protected ?string $date_start = null,
        protected ?string $date_end = null
    ) {}

    /* ========================= QUERY ========================= */
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

        return $q->select(['id','date','reason','detail','total','user_id']);
    }

    /* ========================= LAYOUT ========================= */
    public function headings(): array
    {
        // Item, Fecha, Respons., A, Motivo, S/.
        return ['Item', 'Fecha', 'Respons.', 'A', 'Motivo', 'S/.'];
    }

    public function map($row): array
    {
        $this->i++;

        // Ajusta el contenido de la col. "A" si lo necesitas
        $colA = $row->reason === 'DEUDA' ? 'DEUDA' : 'Taxi Van';

        return [
            $this->i,                                                    // Item
            $row->date ? Carbon::parse($row->date) : null,               // Fecha Excel
            optional($row->user)->name,                                  // Respons.
            $colA,                                                       // A
            trim($row->detail ?: $row->reason),                          // Motivo
            is_null($row->total) ? null : (float)$row->total,            // S/.
        ];
    }

    public function columnFormats(): array
    {
        // A B C D E F
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY, // Fecha dd/mm/aaaa
            // F (S/.) se formatea en AfterSheet
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // El header (que quedará en la fila 2) en negrita (reforzado en AfterSheet)
        return [1 => ['font' => ['bold' => true]]];
    }

    /* ========================= ESTILOS AVANZADOS ========================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Paleta
                $BLUE   = 'FF2874A6'; // header
                $FOOT   = 'FFCEE7FF'; // footer Total
                $WHITE  = 'FFFFFFFF';
                $BORDER = 'FFCFD8DC';

                // Fuente base 10
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);

                // Insertar 1 fila para TÍTULO
                $ws->insertNewRowBefore(1, 1);

                // Título (texto azul, fondo blanco)
                $ws->setCellValue('A1', 'LISTADO GENERAL DE INGRESOS');
                $ws->mergeCells('A1:F1');
                $ws->getRowDimension(1)->setRowHeight(18);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '2874A6']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Filas clave
                $headerRow    = 2;     // encabezados
                $dataStartRow = 3;     // primera fila de datos
                $last         = (int)$ws->getHighestRow();

                // THEAD (azul)
                $ws->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill'      => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2874A6'],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // Anchos compactos
                $ws->getColumnDimension('A')->setWidth(6.0);  // Item
                $ws->getColumnDimension('B')->setWidth(11.0); // Fecha
                $ws->getColumnDimension('C')->setWidth(14.0); // Respons.
                $ws->getColumnDimension('D')->setWidth(10.0); // A
                $ws->getColumnDimension('E')->setWidth(52.0); // Motivo
                $ws->getColumnDimension('F')->setWidth(9.0);  // S/.

                // Wrap sólo en Motivo
                if ($last >= $dataStartRow) {
                    $ws->getStyle("E{$dataStartRow}:E{$last}")
                        ->getAlignment()->setWrapText(true);
                }

                // Congelar bajo el encabezado
                $ws->freezePane("A{$dataStartRow}");

                // SIN filtros (para que no aparezcan los desplegables)
                // (No llamar a setAutoFilter)

                // Alineaciones de datos
                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:D{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("E{$dataStartRow}:E{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("F{$dataStartRow}:F{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                    // Formatos
                    $ws->getStyle("B{$dataStartRow}:B{$last}")
                        ->getNumberFormat()->setFormatCode('d/m/yy');
                    $ws->getStyle("F{$dataStartRow}:F{$last}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0');
                }

                // Bordes finos (header + datos)
                $ws->getStyle("A{$headerRow}:F" . max($headerRow, $last))
                    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);

                // Línea punteada entre filas de datos
                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:F{$last}")
                        ->getBorders()->getHorizontal()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED)
                        ->getColor()->setARGB($BORDER);
                }

                // TOTAL (footer claro)
                $totalRow = max($last, $dataStartRow - 1) + 1;
                $ws->mergeCells("A{$totalRow}:E{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'Total');
                if ($last >= $dataStartRow) {
                    $ws->setCellValue("F{$totalRow}", "=SUM(F{$dataStartRow}:F{$last})");
                } else {
                    $ws->setCellValue("F{$totalRow}", 0);
                }

                // Estilo footer: #CEE7FF
                $ws->getStyle("A{$totalRow}:F{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill'      => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CEE7FF'],
                    ],
                ]);
                $ws->getStyle("F{$totalRow}")
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("F{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0');

                // Borde exterior del bloque completo
                $ws->getStyle("A{$headerRow}:F{$totalRow}")
                    ->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);
            },
        ];
    }
}
