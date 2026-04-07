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
        Schema::create('debt_day_detail_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('debt_day_detail_id');
            $table->string('image_path');
            $table->timestamps();

            $table->foreign('debt_day_detail_id')
                ->references('id')
                ->on('debt_days_detail')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_day_detail_images');
    }
};
