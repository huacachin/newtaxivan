<?php

namespace App\Exports;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth; // <-- agregado
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ExpensesExport implements
    FromQuery, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents, WithColumnWidths, WithTitle
{
    /** contador de item (1..n) */
    private int $i = 0;

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

        // === Restricción por rol: controller solo sus propios registros ===
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $q->where('user_id', $user->id);
        }
        // Nota: admin (u otros) ven todo sin filtro extra.

        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date', [$this->date_start, $this->date_end]);
        } elseif ($this->date_start) {
            $q->where('date', '>=', $this->date_start);
        } elseif ($this->date_end) {
            $q->where('date', '<=', $this->date_end);
        }

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
            'Item',
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
        $this->i++;

        return [
            $this->i,
            $row->date
                ? ExcelDate::dateTimeToExcel(Carbon::parse($row->date)->startOfDay())
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
        return [
            'B' => 'dd/mm/yyyy',
            'E' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4.6,
            'B' => 9.6,
            'C' => 12.0,
            'D' => 36.0,
            'E' => 9.0,
            'F' => 14.0,
            'G' => 14.0,
        ];
    }

    public function title(): string
    {
        return 'Egresos';
    }

    /* ========================= ESTILOS AVANZADOS ========================= */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $BLUE   = 'FF2874A6';
                $FOOT   = 'CEE7FF';
                $BORDER = '000000';

                // Ocultar cuadrícula fuera de la tabla
                $ws->setShowGridLines(false);

                // Altura base compacta
                $ws->getDefaultRowDimension()->setRowHeight(14);

                // Título
                $ws->insertNewRowBefore(1, 1);
                $ws->setCellValue('A1', 'REPORTE DE GASTOS');
                $ws->mergeCells('A1:G1');
                $ws->getRowDimension(1)->setRowHeight(16);
                $ws->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'F80000']],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);

                $headerRow    = 2;
                $dataStartRow = 3;
                $last         = (int)$ws->getHighestRow();

                // THEAD
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

                // Ocultar columnas vacías (H en adelante)
                foreach (range('H', 'Z') as $col) {
                    $ws->getColumnDimension($col)->setVisible(false);
                }

                $ws->freezePane("A{$dataStartRow}");

                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:G{$last}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    $ws->getStyle("B{$dataStartRow}:B{$last}")
                        ->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    $ws->getStyle("E{$dataStartRow}:E{$last}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0');
                }

                $ws->getStyle("A{$headerRow}:G" . max($headerRow, $last))
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);

                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:G{$last}")
                        ->getBorders()->getHorizontal()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED)
                        ->getColor()->setARGB($BORDER);
                }

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

                $ws->getStyle("A{$headerRow}:G{$totalRow}")
                    ->getBorders()->getOutline()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);
            },
        ];
    }
}
