<?php

namespace App\Exports;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

class PaymentsExport implements
    FromQuery, ShouldAutoSize, WithHeadings, WithMapping,
    WithColumnFormatting, WithStyles, WithEvents
{
    public function __construct(
        protected string  $search         = '',
        protected string  $filter         = '',          // '1' placa, '2' usuario, '3' serie, '' mixto
        protected ?string $date_start     = null,        // YYYY-MM-DD
        protected ?string $date_end       = null,        // YYYY-MM-DD
        protected ?string $headquarter_id = '',          // '' = todos
        protected ?string $type           = ''           // '' = todos
    ) {}

    /** ========================= QUERY ========================= */
    public function query(): Builder
    {
        $q = Payment::query()
            ->with([
                'vehicle:id,plate',
                'user:id,name',
                'headquarter:id,name',
            ]);

        // Rango por date_register (si no viene, usar HOY)
        if ($this->date_start && $this->date_end) {
            $q->whereBetween('date_register', [$this->date_start, $this->date_end]);
        } else {
            $today = now()->toDateString();
            $q->whereBetween('date_register', [$this->date_start ?: $today, $this->date_end ?: $today]);
        }

        // Sucursal (opcional)
        if ($this->headquarter_id !== '' && $this->headquarter_id !== null) {
            $q->where('headquarter_id', $this->headquarter_id);
        }

        // Tipo (opcional)
        if ($this->type !== '' && $this->type !== null) {
            $q->where('type', $this->type);
        }

        // Búsqueda según filtro
        $term = trim($this->search);
        if ($term !== '') {
            switch ($this->filter) {
                case '1': // placa
                    $plate = strtoupper($term);
                    $q->where(function ($qq) use ($plate) {
                        $qq->where('legacy_plate', 'like', "%{$plate}%")
                            ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', "%{$plate}%"));
                    });
                    break;

                case '2': // usuario
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%"));
                    break;

                case '3': // serie
                    $q->where('serie', 'like', "%{$term}%");
                    break;

                default:  // mixto
                    $plate = strtoupper($term);
                    $q->where(function ($qq) use ($term, $plate) {
                        $qq->where('serie', 'like', "%{$term}%")
                            ->orWhere('legacy_plate', 'like', "%{$plate}%")
                            ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', "%{$plate}%"))
                            ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%"));
                    });
                    break;
            }
        }

        // ==== Selección de columnas con auto-detección del importe ====
        $table      = (new Payment)->getTable();
        $cols       = Schema::getColumnListing($table);
        $candidates = ['amount','total','total_amount','importe','import','price','value','amount_total'];
        $amountCol  = collect($candidates)->first(fn($c) => in_array($c, $cols, true));

        $select = [
            'id',
            'date_register',
            'hour',
            'serie',
            'type',
            'headquarter_id',
            'vehicle_id',
            'user_id',
            'legacy_plate',
            'created_at',
        ];

        if ($amountCol) {
            $select[] = DB::raw("$table.$amountCol as amount_calc");
        }

        return $q->orderBy('date_register')
            ->orderBy('hour')
            ->select($select);
    }

    /** ========================= LAYOUT / MAPPING ========================= */
    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Serie',
            'Tipo',
            'Sucursal',
            'Placa',
            'Usuario',
            'Importe',
        ];
    }

    public function map($row): array
    {
        $plate  = $row->legacy_plate ?: optional($row->vehicle)->plate;
        $amount = $row->amount_calc ?? null;

        return [
            $row->id,
            $row->date_register
                ? \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(\Carbon\Carbon::parse($row->date_register))
                : null, // Fecha como número de Excel
            $row->serie,
            $row->type,
            optional($row->headquarter)->name,
            $plate,
            optional($row->user)->name,
            is_null($amount) ? null : (float) $amount,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDD2, // yyyy-mm-dd
            'C' => NumberFormat::FORMAT_DATE_TIME3,     // Hora
            'J' => NumberFormat::FORMAT_DATE_DATETIME,  // Creado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // El header quedará en la fila 3 (negrita), se refuerza en AfterSheet
        return [1 => ['font' => ['bold' => true]]];
    }

    /** ========================= ESTILOS “HOMOLOGADOS” ========================= */
    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $e) {
                $ws = $e->sheet->getDelegate();

                $blueDark   = 'FF2874A6';
                $footerFill = 'FFCEE7FF';
                $fontWhite  = 'FFFFFFFF';
                $fontBlack  = 'FF000000';
                $borderSoft = 'FFCFD8DC';

                // 1) Título (fila 1)
                $ws->insertNewRowBefore(1, 1);
                $ws->mergeCells('A1:H1');
                $ws->setCellValue('A1', 'REPORTE DE PAGOS');
                $ws->getStyle('A1:H1')->applyFromArray([
                    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontWhite], 'size'=>10],
                    'alignment' => [
                        'horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $ws->getRowDimension(1)->setRowHeight(18);

                // 2) Header (fila 2) y datos desde 3
                $headerRow    = 2;
                $dataStartRow = 3;
                $last         = (int) $ws->getHighestRow();

                $ws->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
                    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['argb'=>$blueDark]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontWhite], 'size'=>10],
                    'alignment' => [
                        'horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $ws->getRowDimension($headerRow)->setRowHeight(18);

                // Congelar
                $ws->freezePane("A{$dataStartRow}");

                // Sin datos: solo autofiltro
                if ($last < $dataStartRow) {
                    $ws->setAutoFilter("A{$headerRow}:H{$headerRow}");
                    return;
                }

                // 3) Datos: autofiltro, bordes, zebra
                $ws->setAutoFilter("A{$headerRow}:H{$last}");

                $ws->getStyle("A{$headerRow}:H{$last}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $ws->getStyle("A{$headerRow}:H{$last}")
                    ->getBorders()->getAllBorders()->getColor()->setARGB($borderSoft);

                $rangeData = "A{$dataStartRow}:H{$last}";
                $cond = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $cond->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION);
                $cond->setConditions(['MOD(ROW(),2)=0']);
                $cond->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF9FAFB');
                $styles = $ws->getStyle($rangeData)->getConditionalStyles();
                $styles[] = $cond;
                $ws->getStyle($rangeData)->setConditionalStyles($styles);

                // Importe (columna H)
                $ws->getStyle("H{$dataStartRow}:H{$last}")
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("H{$dataStartRow}:H{$last}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

                // 4) Anchos sugeridos
                $ws->getColumnDimension('A')->setWidth(8);   // ID
                $ws->getColumnDimension('B')->setWidth(12);  // Fecha
                $ws->getColumnDimension('C')->setWidth(14);  // Serie
                $ws->getColumnDimension('D')->setWidth(12);  // Tipo
                $ws->getColumnDimension('E')->setWidth(22);  // Sucursal
                $ws->getColumnDimension('F')->setWidth(12);  // Placa
                $ws->getColumnDimension('G')->setWidth(22);  // Usuario
                $ws->getColumnDimension('H')->setWidth(14);  // Importe

                // 5) TOTAL (fila final + 1) con #CEE7FF
                $totalRow = $last + 1;
                $ws->mergeCells("A{$totalRow}:G{$totalRow}");
                $ws->setCellValue("A{$totalRow}", 'TOTAL');
                $ws->setCellValue("H{$totalRow}", "=SUM(H{$dataStartRow}:H{$last})");

                $ws->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor'=>['argb'=>$footerFill]],
                    'font' => ['bold'=>true, 'color'=>['argb'=>$fontBlack], 'size'=>10],
                    'borders' => [
                        'outline' => [
                            'borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                            'color'      => ['argb'=>$blueDark],
                        ]
                    ],
                    'alignment' => ['vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                ]);
                $ws->getStyle("A{$totalRow}:G{$totalRow}")
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $ws->getStyle("H{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                $ws->getRowDimension($totalRow)->setRowHeight(18);
                $ws->getStyle("B{$dataStartRow}:B{$last}")
                    ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $ws->getStyle("B{$dataStartRow}:B{$last}")
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            },
        ];
    }

}
