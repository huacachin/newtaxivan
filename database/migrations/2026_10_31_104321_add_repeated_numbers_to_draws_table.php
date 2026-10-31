<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuántas placas tuvieron que repetir en el mes un número que ya habían
 * tenido (solo pasa por fuerza mayor, con pocos participantes). Queda en el
 * sorteo y se avisa al sortear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->unsignedSmallInteger('repeated_numbers')->default(0)->after('deviations');
        });
    }

    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->dropColumn('repeated_numbers');
        });
    }
};
