<?php

namespace App\Exports;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PaymentsExport implements
    FromQuery, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents, WithTitle
{
    private int $seq = 0; // Item 1..n

    public function __construct(
        protected string  $search         = '',
        protected string  $filter         = '',          // 1=Placa, 2=Usuario, 3=Serie, ''=mixto
        protected ?string $date_start     = null,        // YYYY-MM-DD
        protected ?string $date_end       = null,        // YYYY-MM-DD
        protected ?string $headquarterId  = '',          // '' = todos
        protected ?string $type           = ''           // '' = todos
    ) {}

    /** === Igual que el listado del componente === */
    public function query(): Builder
    {
        $q = Payment::query()
            ->with(['vehicle:id,plate', 'user:id,name', 'headquarter:id,name'])
            ->when($this->date_start && $this->date_end,
                fn($q) => $q->whereBetween('date_register', [$this->date_start, $this->date_end]),
                fn($q) => $q->whereBetween('date_register', [now()->toDateString(), now()->toDateString()])
            )
            ->when($this->headquarterId !== '' && $this->headquarterId !== null,
                fn($q) => $q->where('headquarter_id', $this->headquarterId)
            )
            ->when($this->type !== '',
                fn($q) => $q->where('type', $this->type)
            )
            ->when(trim($this->search) !== '', function ($q) {
                $term  = trim($this->search);
                $plate = strtoupper($term);
                switch ($this->filter) {
                    case '1': // Placa
                        $q->where(function ($qq) use ($plate) {
                            $qq->where('legacy_plate', 'like', '%'.$plate.'%')
                                ->orWhereHas('vehicle', fn($v) => $v->where('plate','like','%'.$plate.'%'));
                        });
                        break;
                    case '2': // Usuario
                        $q->whereHas('user', fn($u) => $u->where('name','like','%'.$term.'%'));
                        break;
                    case '3': // Serie
                        $q->where('serie','like','%'.$term.'%');
                        break;
                    default: // Mixto
                        $q->where(function ($qq) use ($term, $plate) {
                            $qq->where('serie','like','%'.$term.'%')
                                ->orWhere('legacy_plate','like','%'.$plate.'%')
                                ->orWhereHas('vehicle', fn($v) => $v->where('plate','like','%'.$plate.'%'))
                                ->orWhereHas('user', fn($u) => $u->where('name','like','%'.$term.'%'));
                        });
                }
            });

        // === Restricción por rol: controller => solo sus pagos; admin => todos ===
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('controlador')) {
            $q->where('user_id', $user->id);
        }

        return $q->orderBy('date_register')->orderBy('hour')
            ->select([
                'id',
                'vehicle_id',
                'legacy_plate',
                'serie',
                'date_register',
                'date_payment',
                'hour',
                'type',
                'headquarter_id',
                'user_id',
                'amount',
            ]);
    }

    /** === Encabezados === */
    public function headings(): array
    {
        return [
            'Item',           // A
            'Placa',          // B
            'Serie',          // C
            'Fecha Registro', // D
            'Fecha Pago',     // E
            'Hora',           // F
            'Tipo',           // G
            'Sucursal',       // H
            'Usuario',        // I
            'S/',             // J
        ];
    }

    /** Convierte "HH:mm[:ss]" a número de Excel para formateo de hora */
    private function excelTime(?string $hm): ?float
    {
        if (!$hm) return null;
        $parts   = array_map('intval', array_pad(explode(':', $hm), 3, 0));
        $seconds = $parts[0]*3600 + $parts[1]*60 + $parts[2];
        return $seconds / 86400; // día Excel
    }

    public function map($row): array
    {
        $this->seq++;

        $plate = $row->legacy_plate ?: optional($row->vehicle)->plate;

        return [
            $this->seq, // Item
            $plate,
            $row->serie,
            $row->date_register
                ? \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(\Carbon\Carbon::parse($row->date_register))
                : null,
            $row->date_payment
                ? \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(\Carbon\Carbon::parse($row->date_payment))
                : null,
            $this->excelTime($row->hour), // Hora como número Excel
            $row->type,
            optional($row->headquarter)->name,
            optional($row->user)->name,
            (float) $row->amount,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => 'dd/mm/yyyy', // Fecha Registro
            'E' => 'dd/mm/yyyy', // Fecha Pago
            'F' => NumberFormat::FORMAT_DATE_TIME3,     // Hora
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return "Pagos";
    }

    /** === Estilos homologados con diseño de referencia === */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $blueDark   = 'FF2874A6';
                $footerFill = 'FFCEE7FF';
                $white      = 'FFFFFFFF';
                $black      = 'FF000000';
                $gray       = 'FF808080';
                $red        = 'FFF80000';

                // ===== Título (fila 1) =====
                $ws->insertNewRowBefore(1, 1);
                $ws->mergeCells('A1:J1');
                $ws->setCellValue('A1', 'PAGO');
                $ws->getStyle('A1:J1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['argb' => $red],
                        'size'  => 11,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => $black],
                        ],
                    ],
                ]);
                $ws->getRowDimension(1)->setRowHeight(20);

                // ===== Header (fila 2), datos desde fila 3 =====
                $headerRow    = 2;
                $dataStartRow = 3;
                $last         = (int) $ws->getHighestRow();

                $ws->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => $blueDark],
                    ],
                    'font' => [
                        'bold'  => true,
                        'color' => ['argb' => $white],
                        'size'  => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => $white],
                        ],
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => $black],
                        ],
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // ===== Ocultar cuadrícula fuera de la tabla =====
                $ws->setShowGridLines(false);

                // ===== Altura base =====
                $ws->getDefaultRowDimension()->setRowHeight(14);

                if ($last >= $dataStartRow) {
                    // ===== Bordes de datos: punteado horizontal, sólido vertical =====
                    $ws->getStyle("A{$dataStartRow}:J{$last}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_DOTTED,
                                'color'       => ['argb' => $gray],
                            ],
                            'vertical' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['argb' => $black],
                            ],
                            'left' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['argb' => $black],
                            ],
                            'right' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['argb' => $black],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    // ===== Alineaciones =====
                    $ws->getStyle("A{$dataStartRow}:J{$last}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Formato moneda "S/ "
                    $ws->getStyle("J{$dataStartRow}:J{$last}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                    // ===== "Retraso" en rojo (columna G = Tipo) =====
                    for ($r = $dataStartRow; $r <= $last; $r++) {
                        if (strcasecmp((string)$ws->getCell("G{$r}")->getValue(), 'retraso') === 0) {
                            $ws->getStyle("G{$r}")->getFont()->getColor()->setARGB($red);
                        }
                    }

                    // ===== Fila TOTAL =====
                    $totalRow = $last + 1;
                    $ws->mergeCells("A{$totalRow}:I{$totalRow}");
                    $ws->setCellValue("A{$totalRow}", 'TOTAL');
                    $ws->setCellValue("J{$totalRow}", "=SUM(J{$dataStartRow}:J{$last})");

                    $ws->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $footerFill],
                        ],
                        'font' => [
                            'bold'  => true,
                            'color' => ['argb' => $black],
                            'size'  => 10,
                        ],
                        'alignment' => [
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['argb' => $black],
                            ],
                        ],
                    ]);
                    $ws->getStyle("J{$totalRow}")
                        ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                    $ws->getRowDimension($totalRow)->setRowHeight(18);

                    $lastRow = $totalRow;
                } else {
                    $lastRow = $last;
                }

                // ===== Autosize columnas usadas =====
                foreach (range('A', 'J') as $col) {
                    $ws->getColumnDimension($col)->setAutoSize(true);
                }

                // ===== Ocultar columnas vacías (K en adelante) =====
                foreach (range('K', 'Z') as $col) {
                    $ws->getColumnDimension($col)->setVisible(false);
                }

                // ===== Fuente 10 global =====
                $ws->getStyle("A1:J{$lastRow}")->getFont()->setSize(10);
            },
        ];
    }


}
