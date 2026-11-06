<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo al levantar un bloqueo de Sorteos (quién y cuándo ya se guardaban).
 * Los levantados antes de esta columna quedan sin motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->string('unblock_reason')->nullable()->after('unblocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->dropColumn('unblock_reason');
        });
    }
};
