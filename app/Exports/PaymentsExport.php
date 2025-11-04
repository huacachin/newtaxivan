<?php

namespace App\Exports;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PaymentsExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
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

        // === Restricción por rol (igual que en render): admin ve todo; controller solo sus registros
        $user = Auth::user();
        if ($user && !$user->hasRole('admin')) {
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

    /** === Encabezados en el orden solicitado === */
    public function headings(): array
    {
        return [
            'Item',
            'Placa',
            'Serie',
            'Fecha Registro',
            'Fecha Pago',
            'Hora',
            'Tipo',
            'Sucursal',
            'Usuario',
            'S/',
        ];
    }

    /** Convierte "HH:mm[:ss]" a número de Excel para formateo de hora */
    private function excelTime(?string $hm): ?float
    {
        if (!$hm) return null;
        $parts = array_map('intval', array_pad(explode(':', $hm), 3, 0));
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
            'D' => NumberFormat::FORMAT_DATE_YYYYMMDD2, // Fecha Registro
            'E' => NumberFormat::FORMAT_DATE_DATETIME,  // Fecha Pago
            'F' => NumberFormat::FORMAT_DATE_TIME3,     // Hora
            // J (S/) se formatea en AfterSheet con "S/ "
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** === Estilos homologados (sin AutoFilter) === */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $blueDark   = 'FF2874A6';
                $footerFill = 'FFCEE7FF';
                $white  = 'FFFFFFFF';
                $red        = 'FFCC0000';
                $fontBlack  = 'FF000000';
                $borderSoft = 'FFCFD8DC';

                // 1) Título
                $ws->insertNewRowBefore(1, 1);
                $ws->mergeCells('A1:J1');
                $ws->setCellValue('A1', 'PAGOS');
                $ws->getStyle('A1:J1')->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$white]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$red], 'size'=>10],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $ws->getRowDimension(1)->setRowHeight(18);

                // 2) Header (fila 2) y datos desde 3
                $headerRow    = 2;
                $dataStartRow = 3;
                $last         = (int) $ws->getHighestRow();

                $ws->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$white], 'size'=>10],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // Congelar encabezado
                $ws->freezePane("A{$dataStartRow}");

                if ($last < $dataStartRow) {
                    return; // sin datos, sin autofiltros
                }

                // 3) Bordes + zebra (sin AutoFilter)
                $ws->getStyle("A{$headerRow}:J{$last}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $ws->getStyle("A{$headerRow}:J{$last}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderSoft);

                $rangeData = "A{$dataStartRow}:J{$last}";
                $cond = new Conditional();
                $cond->setConditionType(Conditional::CONDITION_EXPRESSION);
                $cond->setConditions(['MOD(ROW(),2)=0']);
                $cond->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF9FAFB');
                $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($rangeData)->setConditionalStyles($styles);

                // Alineaciones
                $ws->getStyle("A{$dataStartRow}:A{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Item
                $ws->getStyle("D{$dataStartRow}:D{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Fecha Registro
                $ws->getStyle("E{$dataStartRow}:E{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Fecha Pago
                $ws->getStyle("F{$dataStartRow}:F{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Hora
                $ws->getStyle("J{$dataStartRow}:J{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // S/

                // Formato moneda "S/ "
                $ws->getStyle("J{$dataStartRow}:J{$last}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                // 4) Anchos
                $ws->getColumnDimension('A')->setWidth(6);   // Item
                $ws->getColumnDimension('B')->setWidth(12);  // Placa
                $ws->getColumnDimension('C')->setWidth(14);  // Serie
                $ws->getColumnDimension('D')->setWidth(14);  // Fecha Registro
                $ws->getColumnDimension('E')->setWidth(18);  // Fecha Pago
                $ws->getColumnDimension('F')->setWidth(10);  // Hora
                $ws->getColumnDimension('G')->setWidth(12);  // Tipo
                $ws->getColumnDimension('H')->setWidth(22);  // Sucursal
                $ws->getColumnDimension('I')->setWidth(22);  // Usuario
                $ws->getColumnDimension('J')->setWidth(14);  // S/

                // 5) TOTAL
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:I{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                $ws->setCellValue("J{$totalRow}", "=SUM(J{$dataStartRow}:J{$last})");

                $ws->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray([
                    'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>$footerFill]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontBlack], 'size'=>10],
                    'borders' => [
                        'outline' => [
                            'borderStyle'=>Border::BORDER_MEDIUM,
                            'color'      => ['argb'=>$blueDark],
                        ]
                    ],
                    'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
                ]);
                $ws->getStyle("A{$totalRow}:I{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("J{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                $ws->getRowDimension($totalRow)->setRowHeight(18);
            },
        ];
    }
}
