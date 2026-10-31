<?php

namespace App\Exports;

use App\Models\DrawEntry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja "Grilla" de la simulación: como la pantalla, un vehículo por fila y un
 * día hábil por columna, con el número coloreado por franja y los conteos
 * B/M/A al final. Arriba va el resumen de la simulación.
 */
class DrawSimulationGridSheet implements FromArray, WithEvents, WithTitle
{
    /** Filas fijas antes de la tabla: título, subtítulo, resumen y una en blanco. */
    private const HEADER_ROW = 5;

    /** Columnas antes de los días: Nº, COD, PLACA, CONDUCTOR, COND. */
    private const LEADING_COLUMNS = 5;

    private const BAND_FILL = [
        DrawEntry::BAND_LOW => ['FFDCFCE7', 'FF166534'],
        DrawEntry::BAND_MID => ['FFFEF3C7', 'FF92400E'],
        DrawEntry::BAND_HIGH => ['FFFEE2E2', 'FF991B1B'],
    ];

    /** Franja de cada celda con número: [fila][columna] => franja. */
    private array $bandAt = [];

    /**
     * @param  array<int, array{date: string, n: int, weekday: string}>  $days
     * @param  array<string, array<int, array>>  $simulated
     * @param  array<string, int>  $summary
     */
    public function __construct(
        protected string $heading,
        protected string $subtitle,
        protected array $days,
        protected Collection $vehicles,
        protected array $simulated,
        protected array $summary,
    ) {}

    public function title(): string
    {
        return 'Grilla';
    }

    public function array(): array
    {
        $s = $this->summary;
        $rows = [
            [$this->heading],
            [$this->subtitle],
            ["{$s['days']} días · {$s['minParticipants']}–{$s['maxParticipants']} participantes por día · {$s['numbers']} números · "
                ."{$s['repeats']} repeticiones de franja · {$s['repeatedNumbers']} números repetidos en el mes · {$s['deviations']} desvíos · "
                ."B/M/A parejo (diferencia de 1 o menos) en {$s['even']} de {$s['vehicles']} vehículos, diferencia máxima {$s['maxGap']}"],
            [''], // Maatwebsite descarta las filas vacías: así queda en blanco
            array_merge(
                ['Nº', 'COD', 'PLACA', 'CONDUCTOR', 'COND.'],
                array_map(fn (array $d) => $d['weekday'].' '.str_pad((string) $d['n'], 2, '0', STR_PAD_LEFT), $this->days),
                ['B', 'M', 'A'],
            ),
        ];

        foreach ($this->vehicles->values() as $i => $vehicle) {
            $rowIndex = self::HEADER_ROW + 1 + $i;
            $counts = array_fill_keys(DrawEntry::BANDS, 0);
            $row = [$i + 1, $vehicle->sort_order, $vehicle->plate, $vehicle->driver->name, $vehicle->condition];

            foreach ($this->days as $d => $day) {
                $entry = $this->simulated[$day['date']][$vehicle->id] ?? null;
                if ($entry === null) {
                    $row[] = '';
                } elseif ($entry['number'] === null) {
                    $row[] = '—';
                } else {
                    $row[] = $entry['deviated'] ? $entry['number'].'*' : $entry['number'];
                    $counts[$entry['band']]++;
                    $this->bandAt[$rowIndex][self::LEADING_COLUMNS + 1 + $d] = $entry['band'];
                }
            }

            $rows[] = array_merge($row, array_values($counts));
        }

        $rows[] = array_merge(
            ['', '', 'PARTICIPAN', '', ''],
            array_map(fn (array $d) => count(array_filter($this->simulated[$d['date']] ?? [], fn (array $e) => $e['number'] !== null)), $this->days),
            ['', '', ''],
        );
        $rows[] = [''];
        $rows[] = ['Verde = número bajo · Amarillo = medio · Rojo = alto · * = no pudo seguir el ciclo ese día · — = no participó (sin check)'];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $ws = $e->sheet->getDelegate();
                $ws->setShowGridLines(false);

                $dayCount = count($this->days);
                $lastColIndex = self::LEADING_COLUMNS + $dayCount + 3;
                $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
                $firstDataRow = self::HEADER_ROW + 1;
                $footerRow = $firstDataRow + $this->vehicles->count();
                $legendRow = $footerRow + 2;

                foreach ([1, 2, 3, $legendRow] as $r) {
                    $ws->mergeCells("A{$r}:{$lastCol}{$r}");
                }
                $ws->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFF80000']]]);
                $ws->getStyle('A2:A3')->applyFromArray(['font' => ['size' => 9, 'color' => ['argb' => 'FF555555']]]);
                $ws->getStyle("A{$legendRow}")->applyFromArray(['font' => ['size' => 9, 'italic' => true, 'color' => ['argb' => 'FF555555']]]);
                $ws->getRowDimension(1)->setRowHeight(22);

                $center = ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER];
                $thinGray = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD9D9D9']];

                $ws->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                    'alignment' => $center + ['wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2874A6']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
                ]);
                $ws->getRowDimension(self::HEADER_ROW)->setRowHeight(28);

                $ws->getStyle("A{$firstDataRow}:{$lastCol}{$footerRow}")->applyFromArray([
                    'font' => ['size' => 9],
                    'alignment' => $center,
                    'borders' => ['allBorders' => $thinGray],
                ]);
                $ws->getStyle("C{$firstDataRow}:C{$footerRow}")->getFont()->setBold(true);
                $ws->getStyle("D{$firstDataRow}:D".($footerRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                foreach ($this->bandAt as $row => $columns) {
                    foreach ($columns as $col => $band) {
                        [$bg, $fg] = self::BAND_FILL[$band];
                        $ws->getStyle(Coordinate::stringFromColumnIndex($col).$row)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => $fg]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                        ]);
                    }
                }

                // Conteos B/M/A con el color de su franja
                foreach (DrawEntry::BANDS as $k => $band) {
                    [$bg, $fg] = self::BAND_FILL[$band];
                    $col = Coordinate::stringFromColumnIndex(self::LEADING_COLUMNS + $dayCount + 1 + $k);
                    $ws->getStyle("{$col}{$firstDataRow}:{$col}".($footerRow - 1))->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => $fg]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    ]);
                }

                $ws->getStyle("A{$footerRow}:{$lastCol}{$footerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCEE7FF']],
                ]);

                $ws->getColumnDimension('A')->setWidth(5);
                $ws->getColumnDimension('B')->setWidth(6);
                $ws->getColumnDimension('C')->setWidth(11);
                $ws->getColumnDimension('D')->setWidth(32);
                $ws->getColumnDimension('E')->setWidth(7);
                for ($c = self::LEADING_COLUMNS + 1; $c <= $lastColIndex; $c++) {
                    $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($c > self::LEADING_COLUMNS + $dayCount ? 5 : 7);
                }

                $ws->freezePane(Coordinate::stringFromColumnIndex(self::LEADING_COLUMNS + 1).$firstDataRow);
            },
        ];
    }
}
