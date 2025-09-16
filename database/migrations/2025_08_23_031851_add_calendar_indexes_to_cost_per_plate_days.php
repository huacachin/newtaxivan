<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cost_per_plate_days', function (Blueprint $t) {
            // Para búsquedas/agrupaciones por vehículo + año/mes/día
            $t->index(['vehicle_id','year','month','date'], 'idx_cpd_vehicle_ymd');
            // Para listados por año/mes y “pintar” el mes
            $t->index(['year','month','date'], 'idx_cpd_ymd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_per_plate_days', function (Blueprint $t) {
            $t->dropIndex('idx_cpd_vehicle_ymd');
            $t->dropIndex('idx_cpd_ymd');
        });
    }
};
