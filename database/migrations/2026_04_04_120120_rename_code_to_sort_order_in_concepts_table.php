<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->renameColumn('code', 'sort_order');
        });

        DB::statement("UPDATE concepts SET sort_order = id WHERE sort_order IS NULL OR sort_order = '' OR sort_order REGEXP '[^0-9]'");

        Schema::table('concepts', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('concepts', function (Blueprint $table) {
            $table->string('sort_order', 50)->change();
        });

        Schema::table('concepts', function (Blueprint $table) {
            $table->renameColumn('sort_order', 'code');
        });
    }
};
