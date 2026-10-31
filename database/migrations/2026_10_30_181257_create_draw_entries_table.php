<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por vehículo en cada sorteo, también los que se quitaron (number y
 * band en NULL). previous_band es la franja de su sorteo anterior y deviated
 * marca a quien no pudo seguir el ciclo bajo -> medio -> alto ese día. La
 * placa se copia para que el historial no cambie si luego se corrige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('plate', 15);
            $table->unsignedSmallInteger('number')->nullable();
            $table->string('band', 4)->nullable();
            $table->string('previous_band', 4)->nullable();
            $table->boolean('deviated')->default(false);
            $table->timestamps();

            $table->unique(['draw_id', 'vehicle_id']);
            $table->unique(['draw_id', 'number']);
            $table->index(['vehicle_id', 'draw_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_entries');
    }
};
