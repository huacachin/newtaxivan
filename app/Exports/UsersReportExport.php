<?php

namespace App\Exports;

use App\Models\User;
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

class UsersReportExport implements
    FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, WithColumnWidths
{
    public function __construct(
        protected ?string $search = null
    ) {}

    private int $rowNum = 0;

    public function query(): Builder
    {
        $search = trim((string) $this->search);

        return User::query()
            ->where('status', 'active')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->with(['headquarter:id,name', 'headquarters:id,name', 'roles:id,name'])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Item', 'Nombre', 'Teléfono', 'Sedes', 'Sede Primaria', 'Rol'];
    }

    public function map($user): array
    {
        $this->rowNum++;

        $sedes = $user->headquarters->pluck('name')->implode(', ') ?: '—';
        $sedePrimaria = optional($user->headquarter)->name ?? '—';
        $rol = optional($user->roles->first())->name ?? '—';

        return [
            $this->rowNum,
            $user->name,
            $user->phone ?: '—',
            $sedes,
            $sedePrimaria,
            $rol,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5.0,
            'B' => 32.0,
            'C' => 14.0,
            'D' => 32.0,
            'E' => 22.0,
            'F' => 14.0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [3 => ['font' => ['bold' => true, 'size' => 10]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {

                $e->sheet->getDelegate()->setTitle('Usuarios');
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $borderC  = 'FFCFD8DC';
                $red      = 'F80000';

                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(15);

                // Insertar fila de título
                $ws->insertNewRowBefore(1, 1);

                $total = $this->rowNum;
                $title = "USUARIOS · TOTAL: {$total}";
                $ws->mergeCells('A1:F1');
                $ws->setCellValue('A1', $title);
                $ws->getStyle('A1:F1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $red]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $white],
                    ],
                ]);
                $ws->getRowDimension(1)->setRowHeight(18);

                $headerRow    = 2;
                $dataStartRow = 3;
                $lastCol      = 'F';

                // Encabezado azul
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $blue],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(17);

                $last = (int) $ws->getHighestRow();

                // Bordes
                $ws->getStyle("A{$headerRow}:{$lastCol}{$last}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB($borderC);

                if ($last >= $dataStartRow) {
                    // Alineaciones
                    $ws->getStyle("A{$dataStartRow}:A{$last}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $ws->getStyle("B{$dataStartRow}:B{$last}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(true);

                    $ws->getStyle("C{$dataStartRow}:C{$last}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $ws->getStyle("D{$dataStartRow}:E{$last}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setWrapText(true);

                    $ws->getStyle("F{$dataStartRow}:F{$last}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Fila TOTAL
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:E{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL USUARIOS');
                $ws->setCellValue("F{$totalRow}", "=COUNT(A{$dataStartRow}:A{$last})");
                $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $footerBg],
                    ],
                    'font'    => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color'       => ['argb' => $blue],
                        ],
                    ],
                ]);
                $ws->getStyle("A{$totalRow}:E{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("F{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Bordes finales sobre todo el rango
                $ws->getStyle("A1:{$lastCol}{$totalRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FF9E9E9E');

                $ws->getStyle("A1:{$lastCol}{$totalRow}")->getFont()->setSize(10);
            },
        ];
    }
}
