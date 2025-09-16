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
        Schema::table('departures', function (Blueprint $t) {
            $t->string('legacy_plate', 20)->nullable()->after('vehicle_id');
            $t->boolean('is_support')->default(false)->after('legacy_plate');
            $t->index('legacy_plate');
            $t->index('is_support');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departures', function (Blueprint $t) {
            $t->dropIndex(['legacy_plate']);
            $t->dropIndex(['is_support']);
            $t->dropColumn(['legacy_plate', 'is_support']);
        });
    }
};
