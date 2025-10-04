<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('headquarter_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('headquarter_id');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->primary(['user_id','headquarter_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('headquarter_id')->references('id')->on('headquarters')->cascadeOnDelete();
        });

        // Backfill desde users.headquarter_id como primaria (si existía)
        DB::statement("
            INSERT INTO headquarter_user (user_id, headquarter_id, is_default, created_at, updated_at)
            SELECT id, headquarter_id, 1, NOW(), NOW()
            FROM users
            WHERE headquarter_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('headquarter_user');
    }
};
