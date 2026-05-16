<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->string('image_path');
            $table->timestamps();

            $table->foreign('driver_id')
                ->references('id')
                ->on('drivers')
                ->onDelete('cascade');
        });

        if (Schema::hasColumn('drivers', 'image_path')) {
            DB::table('drivers')
                ->whereNotNull('image_path')
                ->where('image_path', '<>', '')
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    $now = now();
                    $insert = [];
                    foreach ($rows as $r) {
                        $insert[] = [
                            'driver_id'  => $r->id,
                            'image_path' => $r->image_path,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($insert)) {
                        DB::table('driver_images')->insert($insert);
                    }
                });

            Schema::table('drivers', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'image_path')) {
                $table->string('image_path', 255)->nullable();
            }
        });
        Schema::dropIfExists('driver_images');
    }
};
