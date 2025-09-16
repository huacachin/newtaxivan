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
        Schema::create('expenses', function (Blueprint $table) {

            $table->bigIncrements('id');

            $table->date('date')->nullable();                 // fechaentrada
            $table->string('reason', 150)->nullable();        // aa
            $table->text('detail')->nullable();               // detalle
            $table->decimal('total', 10, 2)->default(0);      // totalgeneral

            $table->foreignId('user_id')                      // usuario (por nombre)
            ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('headquarter_id')               // sucursal (>0) ⇒ FK
            ->nullable()
                ->constrained('headquarters')
                ->nullOnDelete();

            $table->string('document_type', 50)->nullable();  // tipcom
            $table->string('in_charge', 100)->nullable();     // respons

            $table->timestamps();

            // índices útiles
            $table->index('date');
            $table->index('user_id');
            $table->index('headquarter_id');
            $table->index('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
