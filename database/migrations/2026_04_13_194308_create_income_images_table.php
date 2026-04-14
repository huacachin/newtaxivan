<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('income_id');
            $table->string('image_path');
            $table->timestamps();

            $table->foreign('income_id')
                ->references('id')
                ->on('incomes')
                ->onDelete('cascade');
        });

        if (Schema::hasColumn('incomes', 'image_path')) {
            DB::table('incomes')
                ->whereNotNull('image_path')
                ->where('image_path', '<>', '')
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $r) {
                        $insert[] = [
                            'income_id'  => $r->id,
                            'image_path' => $r->image_path,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($insert)) {
                        DB::table('income_images')->insert($insert);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('income_images');
    }
};
