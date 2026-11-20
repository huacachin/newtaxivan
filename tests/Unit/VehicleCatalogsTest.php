<?php

namespace Tests\Unit;

use App\Services\VehicleCatalogs;
use Tests\TestCase;

/**
 * Catálogos del formulario de vehículo: las listas de siempre más lo guardado,
 * sin repetir aunque cambie la caja, y conservando el valor que ya tiene el
 * vehículo (sin tocar la base).
 */
class VehicleCatalogsTest extends TestCase
{
    public function test_stored_options_follow_the_base_list_without_repeating(): void
    {
        $options = VehicleCatalogs::merge(
            ['Particular', 'Taxi Ejecutivo'],
            ['Taxi Estacion', 'TAXI EJECUTIVO', ' Taxi  Remisse ', '', 'taxi estacion'],
        );

        $this->assertSame(['Particular', 'Taxi Ejecutivo', 'Taxi Estacion', 'Taxi Remisse'], $options);
    }

    public function test_a_value_outside_the_catalog_is_offered_first(): void
    {
        $options = VehicleCatalogs::withCurrent(['D2', 'GAS'], 'GNV');

        $this->assertSame(['GNV', 'D2', 'GAS'], $options);
    }

    public function test_a_value_saved_in_another_case_replaces_its_option(): void
    {
        // El <select> compara exacto: con "M3.C1 OMNIBUS" en la lista, un vehículo
        // guardado como "M3.C1 Omnibus" se veía sin valor al editarlo.
        $options = VehicleCatalogs::withCurrent(['M1', 'M3.C1 OMNIBUS', 'M2'], 'M3.C1 Omnibus');

        $this->assertSame(['M1', 'M3.C1 Omnibus', 'M2'], $options);
    }

    public function test_the_catalog_is_left_as_is_for_an_empty_or_listed_value(): void
    {
        $options = ['D2', 'GAS'];

        $this->assertSame($options, VehicleCatalogs::withCurrent($options, null));
        $this->assertSame($options, VehicleCatalogs::withCurrent($options, '  '));
        $this->assertSame($options, VehicleCatalogs::withCurrent($options, 'GAS'));
    }

    public function test_finds_the_existing_option_ignoring_case_and_extra_spaces(): void
    {
        $options = ['H-1 M/BUS', 'GRAND VAN TURISMO'];

        $this->assertSame('H-1 M/BUS', VehicleCatalogs::existing($options, 'H-1 M/Bus'));
        $this->assertSame('GRAND VAN TURISMO', VehicleCatalogs::existing($options, '  grand  van turismo '));
    }

    public function test_does_not_find_a_new_or_empty_value(): void
    {
        $options = ['H-1 M/BUS', 'GRAND VAN TURISMO'];

        $this->assertNull(VehicleCatalogs::existing($options, 'Starex'));
        $this->assertNull(VehicleCatalogs::existing($options, ''));
        $this->assertNull(VehicleCatalogs::existing($options, null));
    }

    public function test_every_catalog_has_a_base_list(): void
    {
        $this->assertSame(VehicleCatalogs::TYPES, array_keys(VehicleCatalogs::BASE));
    }
}
