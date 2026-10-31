<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placas bloqueadas para Sorteos: mientras el bloqueo esté vigente
 * (unblocked_at NULL) el vehículo no entra al sorteo ni a la simulación y en
 * la grilla queda abajo. Desbloquear no borra: deja la fila con quién y
 * cuándo, así queda el historial completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draw_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unblocked_at')->nullable();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'unblocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_blocks');
    }
};
