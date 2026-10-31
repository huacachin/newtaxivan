<?php

use App\Models\Draw;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código público de cada sorteo para su página sin login (/sorteo/{code}),
 * la que se comparte por WhatsApp. Es aleatorio para que no se puedan
 * recorrer los sorteos cambiando un número. Los sorteos previos reciben uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->string('code', 12)->nullable()->unique()->after('id');
        });

        foreach (DB::table('draws')->whereNull('code')->pluck('id') as $id) {
            DB::table('draws')->where('id', $id)->update(['code' => Draw::newCode()]);
        }
    }

    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
