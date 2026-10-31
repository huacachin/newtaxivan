<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueo por días de la semana: NULL = todos los días (bloqueo total);
 * si no, los días en que la placa no entra al sorteo, todas las semanas
 * mientras el bloqueo esté vigente (1 = lunes ... 6 = sábado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->json('weekdays')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }
};
