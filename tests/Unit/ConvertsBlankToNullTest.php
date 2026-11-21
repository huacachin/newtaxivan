<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Traits\ConvertsBlankToNull;
use Tests\TestCase;

/**
 * Un campo vaciado en el formulario llega como '' y la base no lo acepta en
 * números ni fechas: al quitar el conductor de un vehículo, guardar fallaba
 * con "Incorrect integer value: '' for column 'driver_id'" (sin tocar la base).
 */
class ConvertsBlankToNullTest extends TestCase
{
    private function form(array $values): object
    {
        $form = new class
        {
            use ConvertsBlankToNull;

            public $driver_id;

            public $owner_id;

            public $year;

            public $brand;

            /** @param  list<string>  $properties */
            public function normalize(array $properties): void
            {
                $this->blankToNullProps($properties);
            }
        };

        foreach ($values as $property => $value) {
            $form->{$property} = $value;
        }

        return $form;
    }

    public function test_a_blank_field_becomes_null(): void
    {
        $form = $this->form(['driver_id' => '', 'owner_id' => '   ', 'year' => '']);

        $form->normalize(['driver_id', 'owner_id', 'year']);

        $this->assertNull($form->driver_id);
        $this->assertNull($form->owner_id);
        $this->assertNull($form->year);
    }

    public function test_a_filled_field_keeps_its_value(): void
    {
        $form = $this->form(['driver_id' => '82', 'owner_id' => 113, 'year' => 0]);

        $form->normalize(['driver_id', 'owner_id', 'year']);

        $this->assertSame('82', $form->driver_id);
        $this->assertSame(113, $form->owner_id);
        $this->assertSame(0, $form->year); // el cero es un valor, no un vacío
    }

    public function test_only_the_listed_fields_are_touched(): void
    {
        $form = $this->form(['driver_id' => '', 'brand' => '']);

        $form->normalize(['driver_id', 'no_existe']);

        $this->assertNull($form->driver_id);
        $this->assertSame('', $form->brand);
    }

    public function test_the_vehicle_form_converts_its_optional_numbers_and_dates(): void
    {
        foreach (['owner_id', 'driver_id', 'year', 'seats', 'passengers', 'entry_date', 'soat_date', 'certificate_date', 'technical_review'] as $field) {
            $this->assertContains($field, Vehicle::BLANK_AS_NULL);
        }
        // Los textos no entran: '' es válido en ellos y cambiarlo ensuciaría la auditoría
        $this->assertNotContains('brand', Vehicle::BLANK_AS_NULL);
    }
}
