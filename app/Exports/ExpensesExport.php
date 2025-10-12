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
            'user_id','in_charge'
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
            'Responsable'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->date ? Carbon::parse($row->date) : null,          // fecha Excel
            $row->reason,
            $row->detail,
            is_null($row->total) ? null : (float)$row->total,              // número
            optional($row->user)->name,
            $row->in_charge
        ];
    }

    public function columnFormats(): array
    {
        // A B C D E F G H
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY, // dd/mm/yy
            'E' => NumberFormat::FORMAT_NUMBER_00,      // Total
            'H' => NumberFormat::FORMAT_DATE_DATETIME,  // Creado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
    // 5) ESTILOS: ajustado a 7 columnas (A..G), motivo más angosto
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                // Colores
                $BLUE='FF2874A6'; $FOOT='FFCEE7FF'; $WHITE='FFFFFFFF'; $BORDER='FFCFD8DC';

                // ↑ Subimos a 11 pt
                $ws->getParent()->getDefaultStyle()->getFont()->setSize(11);

                // Insertar título
                $ws->insertNewRowBefore(1, 1);
                $headerRow=2; $dataStartRow=3; $lastCol='G';

                // Título (centrado)
                $ws->setCellValue('A1','REPORTE DE GASTOS');
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getRowDimension(1)->setRowHeight(18);
                $ws->getStyle('A1')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>11,'color'=>['rgb'=>'2874A6']],
                    'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                ]);

                // THEAD
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                    'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'2874A6']],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                $ws->freezePane("A{$dataStartRow}");

                // Anchos compactos (↓ Motivo más angosto)
                $ws->getColumnDimension('A')->setWidth(6.0);   // ID
                $ws->getColumnDimension('B')->setWidth(10.0);  // Fecha
                $ws->getColumnDimension('C')->setWidth(10.0);  // Razón
                $ws->getColumnDimension('D')->setWidth(16.0);  // Motivo (más compacto)
                $ws->getColumnDimension('E')->setWidth(9.0);   // Total
                $ws->getColumnDimension('F')->setWidth(14.0);  // Usuario
                $ws->getColumnDimension('G')->setWidth(14.0);  // Responsable

                // SIN filtros

                // Bordes
                $lastRow = max($headerRow,(int)$ws->getHighestRow());
                $ws->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);

                if ($lastRow >= $dataStartRow) {
                    // Wrap en Motivo para que quepa con ancho mínimo
                    $ws->getStyle("D{$dataStartRow}:D{$lastRow}")->getAlignment()->setWrapText(true);

                    // Centrado en todo
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                    // Formatos
                    $ws->getStyle("B{$dataStartRow}:B{$lastRow}")->getNumberFormat()->setFormatCode('d/m/yy');
                    $ws->getStyle("E{$dataStartRow}:E{$lastRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0');

                    // Línea punteada entre filas
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
                        ->getBorders()->getHorizontal()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOTTED)
                        ->getColor()->setARGB($BORDER);
                }

                // TOTAL
                $totalRow = max($lastRow,$dataStartRow-1)+1;
                $ws->mergeCells("A{$totalRow}:D{$totalRow}");
                $ws->setCellValue("A{$totalRow}",'Total');
                $ws->setCellValue("E{$totalRow}", ($lastRow >= $dataStartRow) ? "=SUM(E{$dataStartRow}:E{$lastRow})" : 0);

                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'alignment'=>['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'CEE7FF']],
                ]);
                $ws->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('"S/ " #,##0');

                // Borde exterior
                $ws->getStyle("A{$headerRow}:{$lastCol}{$totalRow}")
                    ->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setARGB($BORDER);
            },
        ];
    }



}
