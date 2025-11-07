<?php

namespace App\Exports;

use App\Models\Expense;
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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ExpensesExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected ?string $search = '',
        protected int $filterType = 1,   // 1=reason, 2=detail, 3=user.name, 4=in_charge
        protected ?string $date_start = null,
        protected ?string $date_end = null
    ) {}

    /* ========================= QUERY ========================= */
    public function query(): Builder
    {
        $q = Expense::query()
            ->with(['user:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        // Rango de fechas
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
                case 4: $q->where('in_charge', 'like', "%{$s}%"); break;
                default: $q->where('reason', 'like', "%{$s}%");
            }
        }

        return $q->select([
            'id','date','reason','detail','total',
            'user_id','in_charge'
        ]);
    }

    /* ========================= LAYOUT ========================= */
    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'A (Razón)',
            'Motivo (Detalle)',
            'Total',
            'Usuario',
            'Responsable',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->date
                ? ExcelDate::dateTimeToExcel(Carbon::parse($row->date)->startOfDay()) // fecha sin hora
                : null,
            $row->reason,
            $row->detail,
            is_null($row->total) ? null : (float)$row->total,
            optional($row->user)->name,
            $row->in_charge,
        ];
    }

    public function columnFormats(): array
    {
        // Columnas A..G
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY, // dd/mm/aaaa
            'E' => NumberFormat::FORMAT_NUMBER_00,     // Total (si quieres sin decimales, cámbialo en AfterSheet)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /* ========================= ESTILOS AVANZADOS ========================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Paleta
                $BLUE   = 'FF2874A6';
                $FOOT   = 'FFCEE7FF';
                $BORDER = 'FFCFD8DC';

                // Fuente base 10 y altura compacta (sin shrink en celdas de texto)
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(13);

                // ===== Título (fila 1)
                $ws->insertNewRowBefore(1, 1);
                $ws->setCellValue('A1', 'REPORTE DE GASTOS');
                $ws->mergeCells('A1:G1');
                $ws->getRowDimension(1)->setRowHeight(16);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '2874A6']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Filas clave
                $headerRow    = 2;
                $dataStartRow = 3;
                $last         = (int)$ws->getHighestRow();

                // ===== THEAD (azul)
                $ws->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
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
                $ws->getRowDimension($headerRow)->setRowHeight(16);

                // ===== Anchos “al ras”
                foreach (range('A','G') as $c) {
                    $ws->getColumnDimension($c)->setAutoSize(false);
                }
                $ws->getColumnDimension('A')->setWidth(6.0);   // ID
                $ws->getColumnDimension('B')->setWidth(9.6);   // Fecha dd/mm/aa
                $ws->getColumnDimension('C')->setWidth(12.0);  // Razón
                $ws->getColumnDimension('D')->setWidth(24.0);  // Motivo (compacto, con wrap)
                $ws->getColumnDimension('E')->setWidth(9.0);   // Total
                $ws->getColumnDimension('F')->setWidth(14.0);  // Usuario
                $ws->getColumnDimension('G')->setWidth(14.0);  // Responsable

                // Congelar bajo encabezado
                $ws->freezePane("A{$dataStartRow}");

                // SIN filtros

                // ===== Alineaciones y formatos
                if ($last >= $dataStartRow) {
                    // A..C,F,G centrado; D izquierda con wrap; E derecha
                    $ws->getStyle("A{$dataStartRow}:C{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("F{$dataStartRow}:G{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("D{$dataStartRow}:D{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(true); // sin shrink
                    $ws->getStyle("E{$dataStartRow}:E{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                    // Formatos explícitos
                    $ws->getStyle("B{$dataStartRow}:B{$last}")
                        ->getNumberFormat()->setFormatCode('d/m/yy');
                    // Si quieres SIN decimales:
                    $ws->getStyle("E{$dataStartRow}:E{$last}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0');
                    // Si los quieres con decimales, usa: '"S/ " #,##0.00'
                }

                // ===== Bordes finos (header + datos)
                $ws->getStyle("A{$headerRow}:G" . max($headerRow, $last))
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);

                // Línea punteada entre filas (suave)
                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:G{$last}")
                        ->getBorders()->getHorizontal()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED)
                        ->getColor()->setARGB($BORDER);
                }

                // ===== TOTAL (footer)
                $totalRow = max($last, $dataStartRow - 1) + 1;
                $ws->mergeCells("A{$totalRow}:D{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'Total');
                $ws->setCellValue("E{$totalRow}", $last >= $dataStartRow
                    ? "=SUM(E{$dataStartRow}:E{$last})"
                    : 0
                );

                $ws->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill'      => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $FOOT],
                    ],
                ]);
                $ws->getStyle("E{$totalRow}")
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("E{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0');

                // Borde exterior del bloque completo
                $ws->getStyle("A{$headerRow}:G{$totalRow}")
                    ->getBorders()->getOutline()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);
            },
        ];
    }
}
