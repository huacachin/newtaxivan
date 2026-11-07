<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanciones de Sorteos: una por falta, con motivo y foto opcional. Cada 2
 * sanciones acumuladas la placa se suspende sola 3 días de sorteo; esas 2
 * quedan ligadas a la suspensión que generaron (draw_block_id). Nunca se borran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->string('photo_path')->nullable();
            $table->foreignId('draw_block_id')->nullable()->constrained('draw_blocks')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'draw_block_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_sanctions');
    }
};
