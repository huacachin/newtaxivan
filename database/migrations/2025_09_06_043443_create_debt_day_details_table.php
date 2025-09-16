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
        Schema::create('debt_days_detail', function (Blueprint $table) {
            // Usamos el id legado como PK (no auto-incremental)
            $table->bigIncrements('id');

            $table->foreignId('debt_days_id')
                ->constrained('debt_days')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // si no existe el padre, no dejamos insertar

            $table->decimal('exonerated', 10, 2)->default(0);
            $table->decimal('amortized', 10, 2)->default(0);
            $table->string('detail', 255)->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->date('date')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['debt_days_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_day_details');
    }
};
