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
        Schema::create('departures', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Fecha y hora del servicio/salida
            $table->date('date');         // yyyy-mm-dd
            $table->time('hour');         // HH:MM:SS

            // Relaciones (tolerantes a importaciones: SET NULL al borrar)
            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Cantidad/veces (si es un conteo de vueltas, por ej.)
            $table->unsignedSmallInteger('times')->default(1);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('headquarter_id')
                ->nullable()
                ->constrained('headquarters')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Importes
            $table->decimal('price', 10, 2)->nullable();    // tarifa, precio total
            $table->decimal('passage', 10, 2)->nullable();  // "pasaje" (si lo manejas separado)

            // Geolocalización
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Pasajeros
            $table->unsignedSmallInteger('passenger')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['date', 'headquarter_id']);
            $table->index(['vehicle_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departures');
    }
};
