<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->string('image_path');
            $table->timestamps();

            $table->foreign('expense_id')
                ->references('id')
                ->on('expenses')
                ->onDelete('cascade');
        });

        if (Schema::hasColumn('expenses', 'image_path')) {
            DB::table('expenses')
                ->whereNotNull('image_path')
                ->where('image_path', '<>', '')
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $r) {
                        $insert[] = [
                            'expense_id' => $r->id,
                            'image_path' => $r->image_path,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($insert)) {
                        DB::table('expense_images')->insert($insert);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_images');
    }
};
