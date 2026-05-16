<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('image_path');
            $table->timestamps();

            $table->foreign('owner_id')
                ->references('id')
                ->on('owners')
                ->onDelete('cascade');
        });

        if (Schema::hasColumn('owners', 'image_path')) {
            DB::table('owners')
                ->whereNotNull('image_path')
                ->where('image_path', '<>', '')
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $r) {
                        $insert[] = [
                            'owner_id'   => $r->id,
                            'image_path' => $r->image_path,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($insert)) {
                        DB::table('owner_images')->insert($insert);
                    }
                });

            Schema::table('owners', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            if (!Schema::hasColumn('owners', 'image_path')) {
                $table->string('image_path', 255)->nullable();
            }
        });
        Schema::dropIfExists('owner_images');
    }
};
