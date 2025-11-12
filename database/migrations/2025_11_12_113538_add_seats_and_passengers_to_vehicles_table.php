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
        Schema::table('vehicles', function (Blueprint $table) {
            // Usamos unsignedSmallInteger por seguridad (0–65535). Ambos nullables.
            $table->unsignedSmallInteger('seats')->nullable();
            $table->unsignedSmallInteger('passengers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['seats', 'passengers']);
        });
    }
};
