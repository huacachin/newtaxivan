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
        Schema::create('payments', function (Blueprint $table) {
           
            $table->bigIncrements('id');

            $table->string('serie')->nullable();
            $table->date('date_register')->nullable();
            $table->date('date_payment')->nullable();

            // FK opcional al vehículo activo; puede ser null (apoyo/inexistente/inactive)
            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('amount', 10, 2)->nullable();
            $table->string('type', 50)->nullable();

            // FK opcionales
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('headquarter_id')
                ->nullable()
                ->constrained('headquarters')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->time('hour')->nullable();
            $table->string('latitude', 32)->nullable();
            $table->string('longitude', 32)->nullable();

            // Igual que departures
            $table->string('legacy_plate', 32)->nullable()->index();
            $table->boolean('is_support')->default(false)->index();

            $table->timestamps();

            // Índices útiles de consulta
            $table->index('date_register');
            $table->index('date_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
