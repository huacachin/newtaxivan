<?php

namespace App\Exports;

use App\Models\Headquarter;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class HeadquartersReportExport implements
    FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, WithColumnWidths
{
    public function __construct(
        protected ?string $search = null
    ) {}

    private int $rowNum = 0;

    public function query(): Builder
    {
        $search = trim((string) $this->search);

        return Headquarter::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Item', 'Nombre', 'Estado'];
    }

    public function map($hq): array
    {
        $this->rowNum++;

        return [
            $this->rowNum,
            $hq->name,
            $hq->status === 'active' ? 'Activo' : 'Inactivo',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6.0,
            'B' => 30.0,
            'C' => 12.0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();
                $ws->setTitle('Sucursales');

                $blue  = 'FF2874A6';
                $white = 'FFFFFFFF';
                $red   = 'F80000';

                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(15);
                $ws->insertNewRowBefore(1, 1);

                $lastCol = 'C';

                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', 'SUCURSALES');
                $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $red]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $white]],
                ]);
                $ws->getRowDimension(1)->setRowHeight(18);

                $headerRow    = 2;
                $dataStartRow = 3;

                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(17);

                $last = (int) $ws->getHighestRow();

                if ($last >= $dataStartRow) {
                    $ws->getStyle("A{$dataStartRow}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getStyle("B{$dataStartRow}:B{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $ws->getStyle("C{$dataStartRow}:C{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $ws->getStyle("A1:{$lastCol}{$headerRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FF000000');

                if ($last >= $dataStartRow) {
                    $borders = $ws->getStyle("A{$dataStartRow}:{$lastCol}{$last}")->getBorders();
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB('FF000000');
                    $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                }

                $ws->getStyle("A1:{$lastCol}{$last}")->getFont()->setSize(10);
            },
        ];
    }
}
