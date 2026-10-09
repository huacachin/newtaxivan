<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca "PEN" (pendiente) en la deuda mensual: un recordatorio manual por
     * fila (vehiculo x mes) para luego filtrarlas. No reemplaza ni modifica la
     * condicion del mes (debt_days.condition) ni la del vehiculo, y no entra en
     * ningun calculo de deuda.
     */
    public function up(): void
    {
        Schema::table('debt_days', function (Blueprint $table) {
            $table->timestamp('pending_at')->nullable()->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('debt_days', function (Blueprint $table) {
            $table->dropColumn('pending_at');
        });
    }
};
