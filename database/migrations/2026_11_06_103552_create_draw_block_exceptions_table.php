<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días desbloqueados dentro de un bloqueo de Sorteos (por ejemplo, el sábado
 * de una placa bloqueada todo el mes): ese día la placa sí entra al sorteo.
 * Cada uno guarda su motivo y quién lo hizo; nunca se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_block_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_block_id')->constrained('draw_blocks')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['draw_block_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_block_exceptions');
    }
};
