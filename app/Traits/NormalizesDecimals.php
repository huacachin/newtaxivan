<?php

namespace App\Traits;

trait NormalizesDecimals
{
    /**
     * Normaliza un string decimal aceptando coma o punto como separador.
     *
     *   "1,50"      => "1.50"
     *   "1,500.00"  => "1500.00"   (coma como separador de miles)
     *   "1.500,25"  => "1500.25"   (formato europeo)
     *   ""          => ""
     *   null        => null
     *   1.5         => 1.5         (números nativos pasan tal cual)
     */
    protected function normalizeDecimal($value)
    {
        if ($value === null || $value === '') return $value;
        if (!is_string($value)) return $value;

        $v = trim($value);
        if ($v === '') return $v;

        $hasDot   = str_contains($v, '.');
        $hasComma = str_contains($v, ',');

        if ($hasDot && $hasComma) {
            // El último separador es el decimal real.
            $lastDot   = strrpos($v, '.');
            $lastComma = strrpos($v, ',');
            if ($lastComma > $lastDot) {
                // Formato europeo "1.500,25" → quitar puntos, coma a punto
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                // Formato anglo "1,500.25" → quitar comas
                $v = str_replace(',', '', $v);
            }
        } elseif ($hasComma) {
            // Sola coma: tratarla como separador decimal
            $v = str_replace(',', '.', $v);
        }

        return $v;
    }

    /**
     * Aplica normalizeDecimal a varias propiedades públicas del componente.
     * Pensado para llamarse al inicio de save()/update() antes de $this->validate().
     */
    protected function normalizeDecimalProps(array $properties): void
    {
        foreach ($properties as $prop) {
            if (property_exists($this, $prop)) {
                $this->{$prop} = $this->normalizeDecimal($this->{$prop});
            }
        }
    }
}
