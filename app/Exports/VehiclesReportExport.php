<?php

namespace App\Exports;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class VehiclesReportExport implements
    FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents
{
    use \App\Traits\CompactColumnWidths;
    public function __construct(
        protected ?string $status = 'active',
        protected ?string $search = null,
        protected ?string $filter = 'plate'
    ) {}

    private int $rowNum = 0;

    private function baseQuery(): Builder
    {
        $status = strtolower(trim((string) $this->status));
        $search = trim((string) $this->search);
        $filter = (string) $this->filter;
        $today  = now()->toDateString();

        return Vehicle::query()
            ->when($status === 'active', function ($q) use ($today) {
                $q->where('status', 'active')
                  ->where(function ($w) use ($today) {
                      $w->whereNull('termination_date')
                        ->orWhere('termination_date', '>', $today);
                  });
            })
            ->when($status === 'inactive', function ($q) use ($today) {
                $q->where(function ($w) use ($today) {
                    $w->where('status', 'inactive')
                      ->orWhere(function ($sub) use ($today) {
                          $sub->where('status', 'active')
                              ->whereNotNull('termination_date')
                              ->where('termination_date', '<=', $today);
                      });
                });
            })
            ->when($search !== '' && $filter !== '', function ($q) use ($filter, $search) {
                return match ($filter) {
                    'brand'     => $q->where('brand', 'like', "%{$search}%"),
                    'owner'     => $q->whereHas('owner',  fn($r) => $r->where('name','like',"%{$search}%")),
                    'driver'    => $q->whereHas('driver', fn($r) => $r->where('name','like',"%{$search}%")),
                    'type'      => $q->where('type','like',"%{$search}%"),
                    'fuel'      => $q->where('fuel','like',"%{$search}%"),
                    'condition' => $q->where('condition','like',"%{$search}%"),
                    'company'   => $q->where('affiliated_company','like',"%{$search}%"),
                    'plate'     => $q->where('plate','like',"%{$search}%"),
                    default     => $q,
                };
            });
    }

    public function query(): Builder
    {
        return $this->baseQuery()
            ->with(['owner:id,name','driver:id,name'])
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('id','asc')
            ->select([
                'id','sort_order','plate','brand','year','class','type','fuel',
                'condition','affiliated_company','owner_id','driver_id','termination_date'
            ]);
    }

    public function headings(): array
    {
        return [
            'Nº','Cod','Placa','Marca','Año','Categoría',
            'Propietario','Conductor','Modalidad','Comb.','Condición','Empresa Afil.'
        ];
    }

    public function map($v): array
    {
        $this->rowNum++;

        return [
            $this->rowNum,
            $v->sort_order ?? $v->id,
            $v->plate,
            $v->brand,
            $v->year,
            $v->class,
            optional($v->owner)->name ?? '—',
            optional($v->driver)->name ?? '—',
            $v->type,
            $v->fuel,
            $v->condition,
            $v->affiliated_company,
        ];
    }

    public function htmlData(): array
    {
        $s = $this->stats();
        $title = 'LISTADO GENERAL DE VEHÍCULOS';
        $stats = $s;

        $showTermination = strtolower(trim((string) $this->status)) === 'inactive';

        $rows = [];
        $i = 0;
        foreach ($this->query()->get() as $v) {
            $i++;
            $rows[] = [
                'item'        => $i,
                'cod'         => $v->sort_order ?? $v->id,
                'plate'       => $v->plate,
                'brand'       => $v->brand,
                'year'        => $v->year,
                'class'       => $v->class,
                'owner'       => optional($v->owner)->name ?? '—',
                'driver'      => optional($v->driver)->name ?? '—',
                'type'        => $v->type,
                'fuel'        => $v->fuel,
                'condition'   => $v->condition,
                'company'     => $v->affiliated_company,
                'termination' => optional($v->termination_date)->format('d/m/Y') ?? '—',
            ];
        }

        return compact('title', 'stats', 'rows', 'showTermination');
    }

    public function styles(Worksheet $sheet)
    {
        return [3 => ['font' => ['bold' => true, 'size' => 10]]];
    }

    private function stats(): array
    {
        $q = $this->baseQuery();

        $total = (clone $q)->count();
        $d2    = (clone $q)->where('fuel', 'D2')->count();
        $gas   = (clone $q)->where('fuel', 'GAS')->count();

        $vt    = (clone $q)->whereIn('fuel', ['GAS','D2'])->count();
        $vqnt  = (clone $q)->where(function ($w) {
            $w->whereNotIn('fuel', ['GAS','D2'])->orWhereNull('fuel');
        })->count();

        $prop = Vehicle::query()
            ->whereRaw('LOWER(TRIM(vehicles.status)) = ?', ['active'])
            ->whereNotNull('owner_id')
            ->join('owners', 'vehicles.owner_id', '=', 'owners.id')
            ->distinct('owners.document_number')
            ->count('owners.document_number');

        $cond = Vehicle::query()
            ->whereRaw('LOWER(TRIM(vehicles.status)) = ?', ['active'])
            ->whereNotNull('driver_id')
            ->join('drivers', 'vehicles.driver_id', '=', 'drivers.id')
            ->distinct('drivers.document_number')
            ->count('drivers.document_number');

        return compact('total','d2','gas','vt','vqnt','prop','cond');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {

                $e->sheet->getDelegate()->setTitle('Vehículos');
                $ws = $e->sheet->getDelegate();

                $blue     = 'FF2874A6';
                $footerBg = 'FFCEE7FF';
                $white    = 'FFFFFFFF';
                $black    = 'FF000000';
                $gray     = 'FF808080';
                $red      = 'FFF80000';

                // Altura base
                $ws->getDefaultRowDimension()->setRowHeight(15);

                // Insertamos dos filas al inicio para título y resumen
                $ws->insertNewRowBefore(1, 2);

                // Stats
                $s = $this->stats();

                // ===== Fila 1: título con stats =====
                $title = sprintf(
                    'VEHÍCULOS %d · D2: %d · Gas: %d · V.T: %d · V.Q.N.T: %d',
                    $s['total'], $s['d2'], $s['gas'], $s['vt'], $s['vqnt']
                );
                $ws->mergeCells('A1:L1');
                $ws->setCellValue('A1', $title);
                $ws->getStyle('A1:L1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => $red]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                // ===== Fila 2: resumen propietario/conductor =====
                $resume = sprintf('Propietario: %d · Conductor: %d', $s['prop'], $s['cond']);
                $ws->mergeCells('A2:L2');
                $ws->setCellValue('A2', $resume);
                $ws->getStyle('A2:L2')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => $black]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension(2)->setRowHeight(16);

                $headerRow    = 3;
                $dataStartRow = 4;
                $lastCol      = 'L';

                // ===== Encabezado azul =====
                $ws->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => $white]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $blue]],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $white]],
                        'outline'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(17);

                // ===== Ocultar cuadrícula =====
                $ws->setShowGridLines(false);

                $last = (int) $ws->getHighestRow();

                if ($last >= $dataStartRow) {
                    // ===== Bordes datos: punteado horizontal, sólido vertical =====
                    $ws->getStyle("A{$dataStartRow}:{$lastCol}{$last}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['argb' => $gray]],
                            'vertical'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'left'       => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                            'right'      => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => $black]],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // ===== Alineaciones =====
                    $ws->getStyle("A{$dataStartRow}:L{$last}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // ===== Fila TOTAL VEHÍCULOS =====
                    $totalRow = $last + 1;
                    $ws->mergeCells("A{$totalRow}:K{$totalRow}");
                    $ws->setCellValue("A{$totalRow}", 'TOTAL VEHÍCULOS');
                    $ws->setCellValue("L{$totalRow}", "=COUNT(A{$dataStartRow}:A{$last})");
                    $ws->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $footerBg]],
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => $black]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $black]],
                        ],
                    ]);
                    $ws->getStyle("A{$totalRow}:L{$totalRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $ws->getRowDimension($totalRow)->setRowHeight(18);

                    $lastRow = $totalRow;
                } else {
                    $lastRow = $last;
                }

                $this->applyCompactWidths($ws, 'A', 'L');

                // ===== Ocultar columnas vacías (M en adelante) =====
                foreach (range('M', 'Z') as $col) {
                    $ws->getColumnDimension($col)->setVisible(false);
                }

                // ===== Fuente 10 global =====
                $ws->getStyle("A1:{$lastCol}{$lastRow}")->getFont()->setSize(10);
            },
        ];
    }


}
