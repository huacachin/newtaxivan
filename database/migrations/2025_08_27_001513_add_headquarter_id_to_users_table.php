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
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'headquarter')) {
                $table->dropColumn('headquarter');
            }

            if (!Schema::hasColumn('users', 'headquarter_id')) {
                $table->foreignId('headquarter_id')
                    ->nullable()
                    ->after('phone')
                    ->constrained('headquarters')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'headquarter_id')) {
                $table->dropConstrainedForeignId('headquarter_id');
            }
        });
    }
};
