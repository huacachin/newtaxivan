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
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module')->nullable()->index()->after('name');
            $table->string('module_label')->nullable()->after('module'); // <-- faltaba
            $table->string('label')->nullable()->after('module_label');
            $table->string('description')->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['module','module_label','label','description']);
        });
    }
};
