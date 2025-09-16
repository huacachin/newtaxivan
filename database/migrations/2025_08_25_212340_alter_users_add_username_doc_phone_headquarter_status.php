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
        Schema::table('users', function (Blueprint $table) {
            // username (login por usuario)
            $table->string('username')->after('name')->unique();

            // document fields
            $table->string('document_type', 20)->nullable()->after('username');   // ej: 'ruc','dni','ce','passport'
            $table->string('document_number', 32)->nullable()->after('document_type')->index();

            // phone
            $table->string('phone', 32)->nullable()->after('document_number');

            // headquarter (sucursal). Mantener simple por ahora (texto). Luego normalizamos a tabla independiente si quieres.
            $table->string('headquarter', 80)->nullable()->after('phone')->index();

            // status
            $table->enum('status', ['active','inactive','suspended'])->default('active')->after('remember_token')->index();

            // email opcional/único
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username','document_type','document_number','phone','headquarter','status']);
            $table->dropUnique(['email']);
        });
    }
};
