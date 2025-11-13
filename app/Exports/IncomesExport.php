<?php

namespace App\Exports;

use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth; // <-- agregado
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class IncomesExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents,WithTitle
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

        // === Restricción por rol: controller solo sus propios registros ===
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controller')) {
            $q->where('user_id', $user->id);
        }
        // admin (u otros) ven todo sin filtro extra.

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
        return ['Item', 'Fecha', 'Respons.', 'A', 'Motivo', 'S/.'];
    }

    public function map($row): array
    {
        $this->i++;
        $colA = $row->reason === 'DEUDA' ? 'DEUDA' : 'Taxi Van';

        return [
            $this->i,                                                    // Item
            $row->date
                ? ExcelDate::dateTimeToExcel(Carbon::parse($row->date)->startOfDay())
                : null,
            optional($row->user)->name,                                  // Respons.
            $colA,                                                       // A
            trim($row->detail ?: $row->reason),                          // Motivo
            is_null($row->total) ? null : (float)$row->total,            // S/.
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY, // dd/mm/aaaa
        ];
    }

    public function title(): string
    {
        return 'Ingresos';
    }

    public function styles(Worksheet $sheet)
    {
        // Header en negrita (fila que quedará en 2 tras insertar el título)
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
                $BORDER = '000000';

                // Fuente base y altura compacta (sin reducción automática del texto)
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(13);

                // ===== Título (fila 1)
                $ws->insertNewRowBefore(1, 1);
                $ws->setCellValue('A1', 'LISTADO GENERAL DE INGRESOS');
                $ws->mergeCells('A1:F1');
                $ws->getRowDimension(1)->setRowHeight(16);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'F80000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Filas clave
                $headerRow    = 2;
                $dataStartRow = 3;
                $last         = (int) $ws->getHighestRow();

                // ===== THEAD
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
                $ws->getRowDimension($headerRow)->setRowHeight(16);

                // ===== Anchos “al ras”
                foreach (range('A', 'F') as $c) {
                    $ws->getColumnDimension($c)->setAutoSize(false);
                }
                $ws->getColumnDimension('A')->setWidth(5.0);
                $ws->getColumnDimension('B')->setWidth(10.0);
                $ws->getColumnDimension('C')->setWidth(14.0);
                $ws->getColumnDimension('D')->setWidth(8.5);
                $ws->getColumnDimension('E')->setWidth(32.0);
                $ws->getColumnDimension('F')->setWidth(9.5);

                // ===== Alineaciones/formatos
                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:D{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("E{$dataStartRow}:E{$last}")
                        ->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                    $ws->getStyle("F{$dataStartRow}:F{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                    $ws->getStyle("B{$dataStartRow}:B{$last}")
                        ->getNumberFormat()->setFormatCode('d/m/yy');
                    $ws->getStyle("F{$dataStartRow}:F{$last}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0');
                }

                // ===== Congelar bajo encabezado
                $ws->freezePane("A{$dataStartRow}");

                // ===== Bordes
                $ws->getStyle("A{$headerRow}:F" . max($headerRow, $last))
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);

                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:F{$last}")
                        ->getBorders()->getHorizontal()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED)
                        ->getColor()->setARGB($BORDER);
                }

                // ===== TOTAL
                $totalRow = max($last, $dataStartRow - 1) + 1;
                $ws->mergeCells("A{$totalRow}:E{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'Total');
                $ws->setCellValue("F{$totalRow}", $last >= $dataStartRow
                    ? "=SUM(F{$dataStartRow}:F{$last})"
                    : 0
                );

                $ws->getStyle("A{$totalRow}:F{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill'      => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $FOOT],
                    ],
                ]);
                $ws->getStyle("F{$totalRow}")
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("F{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0');

                // Borde exterior
                $ws->getStyle("A{$headerRow}:F{$totalRow}")
                    ->getBorders()->getOutline()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);
            },
        ];
    }

}
