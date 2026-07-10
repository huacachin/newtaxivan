<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca "No trabaja": vehiculos que dejaron de operar y esperan ~60 dias
     * antes de la baja definitiva. Es un recordatorio visual (badge); no
     * detiene costos por placa ni deudas. La fecha solo sirve para contar dias.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('not_working_since')->nullable()->after('condition');
        });

        // Data fix: hasta ahora se usaba un conductor comodin "No trabaja" para
        // marcar estos vehiculos. Se migran al nuevo campo y se libera/desactiva
        // el comodin para que no vuelva a usarse.
        $placeholderIds = DB::table('drivers')
            ->whereRaw("LOWER(TRIM(name)) = 'no trabaja'")
            ->pluck('id');

        if ($placeholderIds->isNotEmpty()) {
            DB::table('vehicles')
                ->whereIn('driver_id', $placeholderIds)
                ->whereRaw("LOWER(TRIM(status)) = 'active'")
                ->update([
                    'not_working_since' => now()->toDateString(),
                    'driver_id' => null,
                ]);

            // Vehiculos no activos que apunten al comodin: solo liberar el conductor
            DB::table('vehicles')
                ->whereIn('driver_id', $placeholderIds)
                ->update(['driver_id' => null]);

            DB::table('drivers')
                ->whereIn('id', $placeholderIds)
                ->update(['status' => 'inactive']);
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('not_working_since');
        });
    }
};
