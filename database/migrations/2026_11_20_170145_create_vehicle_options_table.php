<?php

use App\Services\VehicleCatalogs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * En el formulario de vehículo, Categoría, Marca, Modelo, Carrocería, Color,
 * Modalidad y Combustible dejan de ser listas fijas: lo que se escriba nuevo
 * se guarda aquí y sale la próxima vez. La tabla arranca con lo que ya tienen
 * los vehículos registrados y no está en las listas de siempre
 * (VehicleCatalogs::BASE), después de corregir los valores mal escritos.
 */
return new class extends Migration
{
    /**
     * Valores mal escritos en vehicles: columna => [correcto => [mal escritos]].
     *
     * @var array<string, array<string, list<string>>>
     */
    private const CORRECTIONS = [
        'bodywork' => ['MULTIPROPOSITO' => ['MULTIPROPISITO']],
        'class' => ['M1-C3' => ['M1- C3']],
        'brand' => ['Hyundai' => ['Hyunday']],
        'model' => [
            'H-1 M/BUS' => ['H-1M/BUS', 'H-1M /BUS'],
            'MF100' => ['MF 100'],
            'GRAND VAN TURISMO' => ['Gran Van Turismo'],
        ],
    ];

    public function up(): void
    {
        foreach (self::CORRECTIONS as $column => $fixes) {
            foreach ($fixes as $correct => $misspelled) {
                DB::table('vehicles')->whereIn($column, $misspelled)->update([$column => $correct]);
            }
        }

        Schema::create('vehicle_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);   // class | brand | model | bodywork | color | type | fuel
            $table->string('value', 100);
            $table->timestamps();

            $table->unique(['type', 'value']);
        });

        $now = now();
        foreach (VehicleCatalogs::TYPES as $type) {
            $inUse = DB::table('vehicles')->whereNotNull($type)->distinct()->orderBy($type)->pluck($type)->all();
            $base = VehicleCatalogs::BASE[$type];

            foreach (array_slice(VehicleCatalogs::merge($base, $inUse), count($base)) as $value) {
                DB::table('vehicle_options')->insertOrIgnore([
                    'type' => $type,
                    'value' => mb_substr($value, 0, VehicleCatalogs::MAX_LENGTH),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** Las correcciones de vehicles no se deshacen: no se sabe cuál estaba mal escrito. */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_options');
    }
};
