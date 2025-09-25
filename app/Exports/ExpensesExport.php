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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

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
            'user_id','in_charge','created_at'
        ]);
    }

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
            'Creado',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->date ? Carbon::parse($row->date) : null,                 // fecha Excel
            $row->reason,
            $row->detail,
            is_null($row->total) ? null : (float)$row->total,              // número
            optional($row->user)->name,
            $row->in_charge,
            $row->created_at ? Carbon::parse($row->created_at) : null,     // fecha/hora Excel
        ];
    }

    public function columnFormats(): array
    {
        // A B C D E F G H
        return [
            'B' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Fecha
            'E' => NumberFormat::FORMAT_NUMBER_00,      // Total
            'H' => NumberFormat::FORMAT_DATE_DATETIME,  // Creado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Insertamos Título y Subtítulo arriba
                $ws->insertNewRowBefore(1, 2);
                $headerRow    = 3; // fila de headings
                $dataStartRow = 4; // primera fila de datos
                $lastRowNow   = $ws->getHighestRow();
                $lastCol      = 'H'; // A..H

                // Título
                $ws->setCellValue('A1', 'Reporte de Gastos');
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF111827');
                $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Subtítulo (fecha/hora)
                $ws->setCellValue('A2', 'Generado: '.now()->format('Y-m-d H:i'));
                $ws->mergeCells("A2:{$lastCol}2");
                $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF6B7280');
                $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Encabezado celeste + centrado
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE9F4FF');
                $ws->getRowDimension($headerRow)->setRowHeight(22);
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Congelar encabezado
                $ws->freezePane("A{$dataStartRow}");

                // Anchos de columna amigables
                $ws->getColumnDimension('A')->setWidth(8);   // ID
                $ws->getColumnDimension('B')->setWidth(12);  // Fecha
                $ws->getColumnDimension('C')->setWidth(26);  // Razón
                $ws->getColumnDimension('D')->setWidth(38);  // Detalle
                $ws->getColumnDimension('E')->setWidth(14);  // Total
                $ws->getColumnDimension('F')->setWidth(22);  // Usuario
                $ws->getColumnDimension('G')->setWidth(22);  // Responsable
                $ws->getColumnDimension('H')->setWidth(18);  // Creado

                // Última fila de datos (después de insertar 2 filas)
                $lastRow = $ws->getHighestRow();
                if ($lastRow < $headerRow) $lastRow = $headerRow;

                // Autofiltro en el bloque principal
                $ws->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");

                // Bordes finos
                $ws->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCBD5E1');

                // Zebra stripes sobre las filas de datos
                if ($lastRow >= $dataStartRow) {
                    $cond = new Conditional();
                    $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $cond->setConditions(['MOD(ROW(),2)=0']);
                    $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF8FAFC');

                    $range = "A{$dataStartRow}:{$lastCol}{$lastRow}";
                    $styles = $ws->getStyle($range)->getConditionalStyles();
                    $styles[] = $cond;
                    $ws->getStyle($range)->setConditionalStyles($styles);
                }

                // Alineación: texto a la izquierda; números a la derecha; fechas centradas
                if ($lastRow >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ID
                    $ws->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Fecha
                    $ws->getStyle("C{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Razón/Detalle
                    $ws->getStyle("E{$dataStartRow}:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // Total
                    $ws->getStyle("F{$dataStartRow}:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Usuario/Responsable
                    $ws->getStyle("H{$dataStartRow}:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Creado
                }

                // Fila de totales (columna E)
                $totalRow = max($lastRow, $headerRow) + 1;
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                if ($lastRow >= $dataStartRow) {
                    $ws->setCellValue("E{$totalRow}", "=SUM(E{$dataStartRow}:E{$lastRow})");
                } else {
                    $ws->setCellValue("E{$totalRow}", 0);
                }

                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFCEE7FF');
                $ws->getStyle("E{$totalRow}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                $ws->getStyle("E{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Bordes incluyendo la fila TOTAL
                $ws->getStyle("A{$headerRow}:{$lastCol}{$totalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FFCBD5E1');
            },
        ];
    }
}
