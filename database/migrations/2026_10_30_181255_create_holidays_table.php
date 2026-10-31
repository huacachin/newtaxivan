<?php

use App\Models\Holiday;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feriados y días no laborables. Sorteos los salta: solo sortea de lunes a
 * sábado fuera de esta tabla. Deja cargados los feriados nacionales de 2026 y
 * 2027; los demás años se cargan con un botón desde el propio módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('description', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        foreach ([2026, 2027] as $year) {
            foreach (Holiday::nationalFor($year) as $date => $description) {
                DB::table('holidays')->insertOrIgnore([
                    'date' => $date,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
