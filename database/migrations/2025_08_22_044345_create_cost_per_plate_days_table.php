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
        Schema::create('cost_per_plate_days', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('date');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['vehicle_id','date'], 'uniq_vehicle_date');
            $table->index(['vehicle_id','year','month'], 'idx_vehicle_year_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_per_plate_days');
    }
};
