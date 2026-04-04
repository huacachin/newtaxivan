<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropUnique('owners_document_type_document_number_unique');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique('drivers_document_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->unique(['document_type', 'document_number']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->unique('document_number');
        });
    }
};
