<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait CompactColumnWidths
{
    protected function applyCompactWidths(Worksheet $ws, string $firstCol, string $lastCol, float $factor = 0.85, float $min = 3.0, float $max = 40.0, int $startRow = 2): void
    {
        $lastRow = (int) $ws->getHighestRow();
        if ($lastRow < $startRow) return;

        foreach (range($firstCol, $lastCol) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(false);
            $maxLen = 0;

            for ($row = $startRow; $row <= $lastRow; $row++) {
                $val = (string) $ws->getCell("{$col}{$row}")->getFormattedValue();
                $len = function_exists('mb_strwidth') ? mb_strwidth($val, 'UTF-8') : strlen($val);
                if ($len > $maxLen) $maxLen = $len;
            }

            $width = max($min, min($max, $maxLen * $factor + 0.5));
            $ws->getColumnDimension($col)->setWidth($width);
        }
    }
}
