<?php

namespace App\Exports;

use App\Models\User;
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

class UsersReportExport implements
    FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents
{
    use \App\Traits\CompactColumnWidths;
    public function __construct(
        protected ?string $search = null
    ) {}

    private int $rowNum = 0;

    public function query(): Builder
    {
        $search = trim((string) $this->search);

        return User::query()
            ->where('status', 'active')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'director'))
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
        return ['Nº', 'Nombres', 'Telefono', 'Sedes', 'Sede Primaria', 'Nivel'];
    }

    public function map($user): array
    {
        $this->rowNum++;

        $sedes = $user->headquarters->pluck('name')->implode(', ') ?: '—';
        $sedePrimaria = optional($user->headquarter)->name ?? '—';
        $rolKey = optional($user->roles->first())->name;
        $rol    = $rolKey ? __('roles.' . $rolKey, [], 'es') : '—';

        return [
            $this->rowNum,
            $user->name,
            $user->phone ?: '—',
            $sedes,
            $sedePrimaria,
            $rol,
        ];
    }


    public function htmlData(): array
    {
        $users = User::query()
            ->where('status', 'active')
            ->when(trim((string) $this->search) !== '', function ($q) {
                $term = trim((string) $this->search);
                $q->where(function ($w) use ($term) {
                    $w->where('username', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->with(['headquarter:id,name', 'headquarters:id,name', 'roles:id,name', 'permissions'])
            ->orderBy('name')
            ->get();

        $rows = [];
        $i = 0;
        foreach ($users as $user) {
            $i++;
            $rolKey = optional($user->roles->first())->name;
            $rol    = $rolKey ? __('roles.' . $rolKey, [], 'es') : '—';

            // Sede combinada como la vista
            $isTopRole = in_array($rolKey, ['director', 'gerente']);
            if ($isTopRole) {
                $sede = '—';
            } else {
                $sedes = $user->headquarters->pluck('name')->implode(', ') ?: '—';
                $primaria = optional($user->headquarter)->name;
                $sede = $sedes;
                if ($primaria) {
                    $sede .= ' (Primaria: ' . $primaria . ')';
                }
            }

            $rows[] = [
                'item'     => $i,
                'name'     => $user->name,
                'username' => $user->username,
                'phone'    => $user->phone ?: '—',
                'sede'     => $sede,
                'rol'      => $rol,
                'permisos' => $user->permissions->count(),
            ];
        }
        return ['rows' => $rows, 'total' => count($rows)];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {

                $e->sheet->getDelegate()->setTitle('Usuarios');
                $ws = $e->sheet->getDelegate();

                $blue    = 'FF2874A6';
                $white   = 'FFFFFFFF';
                $borderC = 'FFCFD8DC';
                $red     = 'F80000';

                $ws->getParent()->getDefaultStyle()->getFont()->setSize(10);
                $ws->getDefaultRowDimension()->setRowHeight(15);

                // Insertar fila de título
                $ws->insertNewRowBefore(1, 1);

                $lastCol      = 'F';

                // Fila 1: título
                $title = 'LISTADO GENERAL DE USUARIO';
                $ws->mergeCells("A1:{$lastCol}1");
                $ws->setCellValue('A1', $title);
                $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
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
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $ws->getStyle("F{$dataStartRow}:F{$last}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Bordes negros sólidos en título + header
                $ws->getStyle("A1:{$lastCol}{$headerRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('FF000000');

                // Bordes en filas de datos: verticales sólidos, horizontales discontinuos
                if ($last >= $dataStartRow) {
                    $dataRange = "A{$dataStartRow}:{$lastCol}{$last}";
                    $borders   = $ws->getStyle($dataRange)->getBorders();

                    // Contorno sólido
                    $borders->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                    $borders->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');

                    // Líneas horizontales internas (entre filas): discontinuas
                    $borders->getHorizontal()->setBorderStyle(Border::BORDER_DASHED)->getColor()->setARGB('FF000000');

                    // Líneas verticales internas (entre columnas): sólidas
                    $borders->getVertical()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                }

                $ws->getStyle("A1:{$lastCol}{$last}")->getFont()->setSize(10);

                $this->applyCompactWidths($ws, 'A', $lastCol);
            },
        ];
    }
}
