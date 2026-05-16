<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->string('image_path');
            $table->timestamps();

            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->onDelete('cascade');
        });

        if (Schema::hasColumn('vehicles', 'image_path')) {
            DB::table('vehicles')
                ->whereNotNull('image_path')
                ->where('image_path', '<>', '')
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $r) {
                        $insert[] = [
                            'vehicle_id' => $r->id,
                            'image_path' => $r->image_path,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($insert)) {
                        DB::table('vehicle_images')->insert($insert);
                    }
                });

            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'image_path')) {
                $table->string('image_path', 255)->nullable();
            }
        });
        Schema::dropIfExists('vehicle_images');
    }
};
