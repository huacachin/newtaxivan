<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replica el campo image_path (que ya existe en drivers) en las tablas
 * vehicles y owners para soportar la subida de foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'image_path')) {
                $table->string('image_path', 255)->nullable()->after('detail');
            }
        });

        Schema::table('owners', function (Blueprint $table) {
            if (!Schema::hasColumn('owners', 'image_path')) {
                $table->string('image_path', 255)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });

        Schema::table('owners', function (Blueprint $table) {
            if (Schema::hasColumn('owners', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
