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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->integer('sort_order')->nullable();

            $table->string('plate', 15)->unique();
            $table->string('headquarters', 150)->nullable();

            $table->date('entry_date')->nullable();
            $table->date('termination_date')->nullable();

            $table->string('class', 50)->nullable();
            $table->string('brand', 100)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('model', 100)->nullable();
            $table->string('bodywork', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('type', 50)->nullable();

            $table->string('affiliated_company', 150)->nullable();
            $table->string('condition', 100)->nullable();

            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('owners')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('drivers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('fuel', 50)->nullable();

            $table->date('soat_date')->nullable();
            $table->date('certificate_date')->nullable();
            $table->date('technical_review')->nullable();

            $table->text('detail')->nullable();

            // Sugerencia: usa 'valid' | 'expired'
            $table->string('validity_status', 20)->nullable();

            // Sugerencia: 'active' | 'inactive' | 'pending' según tu flujo
            $table->string('status', 50)->default('active');

            $table->timestamps();

            // Índices útiles
            $table->index(['owner_id', 'driver_id']);
            $table->index('status');
            $table->index('validity_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
