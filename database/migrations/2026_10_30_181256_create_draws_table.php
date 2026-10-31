<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un sorteo por día hábil. Anular no borra: deja el sorteo con quién, cuándo
 * y por qué, y libera el día. active_date vale la fecha mientras el sorteo
 * está vigente y NULL al anularlo; su índice único impide dos sorteos
 * vigentes el mismo día aunque dos usuarios pulsen Sortear a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->date('active_date')->nullable()->unique();
            $table->unsignedSmallInteger('participants');
            $table->unsignedSmallInteger('deviations')->default(0);
            $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('annul_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draws');
    }
};
