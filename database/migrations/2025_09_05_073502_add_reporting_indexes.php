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
        // vehicles: siempre filtras por status y a veces por condition, y ordenas por sort_order
        Schema::table('vehicles', function (Blueprint $table) {
            $table->index(['status', 'condition', 'sort_order'], 'idx_vehicles_status_condition_sort');
            // Único por placa (si aún no existe):
            // $table->unique('plate', 'vehicles_plate_unique');
        });

        // cost_per_plate_days: lookups por vehículo + fecha
        Schema::table('cost_per_plate_days', function (Blueprint $table) {
            $table->index(['vehicle_id', 'date'], 'idx_cppd_vehicle_date');
        });

        // payments: consultas por vehicle_id + rango de date_payment (+ type)
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['vehicle_id', 'date_payment', 'type'], 'idx_payments_vehicle_date_type');
            // Si haces búsqueda por legacy_plate (texto), puedes añadir:
            // $table->index('legacy_plate', 'idx_payments_legacy_plate');
        });

        // departures: agregaciones por vehicle_id + date y filtro por sede
        Schema::table('departures', function (Blueprint $table) {
            $table->index(['vehicle_id', 'date'], 'idx_departures_vehicle_date');
            $table->index('headquarter_id', 'idx_departures_hq');
            // Si consultas seguido por is_support:
            // $table->index(['is_support', 'date'], 'idx_departures_support_date');
        });

        // headquarters: si filtras por nombre en importaciones/mapeos
        Schema::table('headquarters', function (Blueprint $table) {
            $table->index('name', 'idx_headquarters_name');
        });

        // users: si haces match por name en importaciones
        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'idx_users_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('idx_vehicles_status_condition_sort');
            // $table->dropUnique('vehicles_plate_unique');
        });

        Schema::table('cost_per_plate_days', function (Blueprint $table) {
            $table->dropIndex('idx_cppd_vehicle_date');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_vehicle_date_type');
            // $table->dropIndex('idx_payments_legacy_plate');
        });

        Schema::table('departures', function (Blueprint $table) {
            $table->dropIndex('idx_departures_vehicle_date');
            $table->dropIndex('idx_departures_hq');
            // $table->dropIndex('idx_departures_support_date');
        });

        Schema::table('headquarters', function (Blueprint $table) {
            $table->dropIndex('idx_headquarters_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_name');
        });
    }
};
