<?php

namespace App\Exports;

use App\Models\DrawEntry;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja "Orden por día" de la simulación: una fila por posición de salida y
 * una columna por día; cada celda es la placa que salió con ese número,
 * coloreada por franja.
 */
class DrawSimulationOrderSheet implements FromArray, WithEvents, WithTitle
{
    /** Título y una fila en blanco antes de la tabla. */
    private const HEADER_ROW = 3;

    private const BAND_FILL = [
        DrawEntry::BAND_LOW => ['FFDCFCE7', 'FF166534'],
        DrawEntry::BAND_MID => ['FFFEF3C7', 'FF92400E'],
        DrawEntry::BAND_HIGH => ['FFFEE2E2', 'FF991B1B'],
    ];

    private array $bandAt = [];

    private int $positions = 0;

    /**
     * @param  array<int, array{date: string, n: int, weekday: string}>  $days
     * @param  array<string, array<int, array>>  $simulated
     */
    public function __construct(
        protected string $heading,
        protected array $days,
        protected array $simulated,
    ) {}

    public function title(): string
    {
        return 'Orden por día';
    }

    public function array(): array
    {
        // [fecha][número] => [placa, franja]
        $byNumber = [];
        foreach ($this->days as $day) {
            foreach ($this->simulated[$day['date']] ?? [] as $entry) {
                if ($entry['number'] !== null) {
                    $byNumber[$day['date']][$entry['number']] = [$entry['plate'], $entry['band']];
                    $this->positions = max($this->positions, $entry['number']);
                }
            }
        }

        $rows = [
            ['ORDEN DE SALIDA POR DÍA - '.$this->heading],
            [''], // Maatwebsite descarta las filas vacías: así queda en blanco
            array_merge(['Nº'], array_map(fn (array $d) => $d['weekday'].' '.str_pad((string) $d['n'], 2, '0', STR_PAD_LEFT), $this->days)),
        ];

        for ($number = 1; $number <= $this->positions; $number++) {
            $row = [$number];
            foreach ($this->days as $d => $day) {
                [$plate, $band] = $byNumber[$day['date']][$number] ?? ['', null];
                $row[] = $plate;
                if ($band !== null) {
                    $this->bandAt[self::HEADER_ROW + $number][2 + $d] = $band;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();
                $ws->setShowGridLines(false);

                $lastColIndex = 1 + count($this->days);
                $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
                $firstDataRow = self::HEADER_ROW + 1;
                $lastRow = self::HEADER_ROW + $this->positions;
                $center = ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER];

                $ws->mergeCells("A1:{$lastCol}1");
                $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFF80000']]]);
                $ws->getRowDimension(1)->setRowHeight(22);

                $ws->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                    'alignment' => $center + ['wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2874A6']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
                ]);
                $ws->getRowDimension(self::HEADER_ROW)->setRowHeight(28);

                if ($this->positions > 0) {
                    $ws->getStyle("A{$firstDataRow}:{$lastCol}{$lastRow}")->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => $center,
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD9D9D9']]],
                    ]);
                    $ws->getStyle("A{$firstDataRow}:A{$lastRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2874A6']],
                    ]);
                }

                foreach ($this->bandAt as $row => $columns) {
                    foreach ($columns as $col => $band) {
                        [$bg, $fg] = self::BAND_FILL[$band];
                        $ws->getStyle(Coordinate::stringFromColumnIndex($col).$row)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => $fg]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                        ]);
                    }
                }

                $ws->getColumnDimension('A')->setWidth(5);
                for ($c = 2; $c <= $lastColIndex; $c++) {
                    $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(9);
                }
                $ws->freezePane('B'.$firstDataRow);
            },
        ];
    }
}
