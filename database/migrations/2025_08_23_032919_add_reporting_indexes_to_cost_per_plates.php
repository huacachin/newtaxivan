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
        Schema::table('cost_per_plates', function (Blueprint $t) {
            $t->index(['year','month'], 'idx_cpp_year_month');
            $t->index(['year','month','vehicle_id'], 'idx_cpp_year_month_vehicle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_per_plates', function (Blueprint $t) {
            $t->dropIndex('idx_cpp_year_month');
            $t->dropIndex('idx_cpp_year_month_vehicle');
        });
    }
};
