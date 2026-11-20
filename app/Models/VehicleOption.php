<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Opción de un catálogo del formulario de vehículo (categoría, marca, modelo,
 * carrocería, color, modalidad o combustible). La tabla crece sola con lo que
 * se escribe en los formularios (ver App\Services\VehicleCatalogs).
 */
class VehicleOption extends Model
{
    protected $fillable = ['type', 'value'];
}
