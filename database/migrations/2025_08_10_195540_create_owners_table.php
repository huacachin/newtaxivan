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
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('document_type', 20);           // ej: DNI, RUC, CE, PASS
            $table->string('document_number', 50);
            $table->date('document_expiration_date')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('status', 50)->default('activo'); // active | inactive
            $table->timestamps();

            // índices útiles
            $table->unique(['document_type', 'document_number']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
