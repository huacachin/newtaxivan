<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conductor del vehículo al momento del sorteo, para la tarjeta "Orden de
 * salida": si luego cambia de conductor, el sorteo sigue mostrando quién
 * manejaba ese día. Las filas previas se llenan con el conductor actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->string('driver_name', 150)->nullable()->after('plate');
        });

        DB::table('draw_entries as e')
            ->join('vehicles as v', 'v.id', '=', 'e.vehicle_id')
            ->join('drivers as d', 'd.id', '=', 'v.driver_id')
            ->whereNull('e.driver_name')
            ->update(['e.driver_name' => DB::raw('d.name')]);
    }

    public function down(): void
    {
        Schema::table('draw_entries', function (Blueprint $table) {
            $table->dropColumn('driver_name');
        });
    }
};
