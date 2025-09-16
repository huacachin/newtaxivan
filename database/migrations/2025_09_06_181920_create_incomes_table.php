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
        Schema::create('incomes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');                                   // fechaentrada
            $table->string('reason', 191)->nullable();              // aa
            $table->text('detail')->nullable();                     // detalle
            $table->decimal('total', 12, 2)->default(0);            // totalgeneral
            $table->foreignId('user_id')                            // usuario -> users.name
            ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
