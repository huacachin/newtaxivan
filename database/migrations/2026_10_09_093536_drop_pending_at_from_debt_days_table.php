<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se descarta la marca manual "PEN" por clic derecho: el filtro PEN de la
     * deuda mensual pasa a ser "con deuda pendiente" (total - exonerado -
     * amortizado > 0), calculado, sin columna propia.
     */
    public function up(): void
    {
        Schema::table('debt_days', function (Blueprint $table) {
            $table->dropColumn('pending_at');
        });
    }

    public function down(): void
    {
        Schema::table('debt_days', function (Blueprint $table) {
            $table->timestamp('pending_at')->nullable()->after('condition');
        });
    }
};
