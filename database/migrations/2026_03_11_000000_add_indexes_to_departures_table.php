<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->index('user_id', 'idx_departures_user_id');
            $table->index(['is_support', 'date'], 'idx_departures_is_support_date');
            $table->index(['date', 'user_id'], 'idx_departures_date_user');
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->dropIndex('idx_departures_user_id');
            $table->dropIndex('idx_departures_is_support_date');
            $table->dropIndex('idx_departures_date_user');
        });
    }
};
