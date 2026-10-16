<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de seguridad de segundo nivel. La primera ('director-role') protege los
 * cambios de rol que entran o salen de Director. Solo se guarda el hash; se crea
 * o cambia con el comando users:director-key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('hash');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_keys');
    }
};
