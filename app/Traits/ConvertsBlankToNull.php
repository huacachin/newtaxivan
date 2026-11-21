<?php

namespace App\Traits;

/**
 * Un campo que el usuario deja vacío llega al componente como '' (Livewire no
 * aplica ConvertEmptyStringsToNull) y pasa la validación `nullable`, pero la
 * base rechaza '' en columnas de número o fecha ("Incorrect integer value: ''
 * for column 'driver_id'"): pasaba al quitar el conductor de un vehículo.
 */
trait ConvertsBlankToNull
{
    /**
     * Pasa a null las propiedades públicas que vienen vacías. Pensado para
     * llamarse al inicio de save()/update(), antes de $this->validate().
     *
     * @param  list<string>  $properties
     */
    protected function blankToNullProps(array $properties): void
    {
        foreach ($properties as $property) {
            if (property_exists($this, $property) && is_string($this->{$property}) && trim($this->{$property}) === '') {
                $this->{$property} = null;
            }
        }
    }
}
