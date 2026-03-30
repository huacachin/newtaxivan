<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait CompactColumnWidths
{
    protected function applyCompactWidths(Worksheet $ws, string $firstCol, string $lastCol, float $factor = 0.85, float $min = 3.0, float $max = 40.0): void
    {
        $lastRow = (int) $ws->getHighestRow();
        if ($lastRow < 1) return;

        // Recopilar celdas mergeadas para ignorarlas
        $mergedCells = [];
        foreach ($ws->getMergeCells() as $range) {
            $cells = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::extractAllCellReferencesInRange($range);
            // Ignorar la primera celda del merge (esa sí tiene contenido), marcar las demás
            array_shift($cells);
            foreach ($cells as $cell) {
                $mergedCells[$cell] = true;
            }
            // También marcar la primera si el merge cruza múltiples columnas
            $firstCell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::extractAllCellReferencesInRange($range)[0] ?? null;
            if ($firstCell) {
                preg_match('/^([A-Z]+)/', $firstCell, $mFirst);
                $lastCell = end($cells) ?: $firstCell;
                preg_match('/^([A-Z]+)/', $lastCell, $mLast);
                if (($mFirst[1] ?? '') !== ($mLast[1] ?? '')) {
                    // El merge cruza columnas, ignorar la primera también
                    $mergedCells[$firstCell] = true;
                }
            }
        }

        foreach (range($firstCol, $lastCol) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(false);
            $maxLen = 0;

            for ($row = 1; $row <= $lastRow; $row++) {
                $ref = "{$col}{$row}";
                if (isset($mergedCells[$ref])) continue;

                $val = (string) $ws->getCell($ref)->getFormattedValue();
                $len = function_exists('mb_strwidth') ? mb_strwidth($val, 'UTF-8') : strlen($val);
                if ($len > $maxLen) $maxLen = $len;
            }

            $width = max($min, min($max, $maxLen * $factor + 0.5));
            $ws->getColumnDimension($col)->setWidth($width);
        }
    }
}
