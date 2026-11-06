<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada bloqueo de Sorteos pasa a tener su periodo:
 *  - indefinite: desde starts_on hasta que lo desbloqueen (ends_on NULL)
 *  - month:      todo un mes (starts_on = día 1, ends_on = último día)
 *  - range:      de starts_on a ends_on
 *  - dates:      solo los días de `dates` (starts_on/ends_on = el primero y el último)
 *
 * Los que ya existían se adaptan: los totales quedan "hasta desbloquearla"
 * desde el día en que se crearon, y los de días de la semana (que se repetían
 * todas las semanas) quedan como días específicos de la semana de lunes a
 * domingo en que se registraron. Después se quita la columna weekdays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->string('kind', 20)->default('indefinite')->after('reason');
            $table->date('starts_on')->nullable()->after('kind');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->json('dates')->nullable()->after('ends_on');
            $table->index(['vehicle_id', 'starts_on', 'ends_on']);
        });

        foreach (DB::table('draw_blocks')->orderBy('id')->get(['id', 'weekdays', 'created_at']) as $block) {
            $created = Carbon::parse($block->created_at)->startOfDay();
            $weekdays = $block->weekdays ? json_decode($block->weekdays, true) : null;

            if (! $weekdays) {
                $values = ['kind' => 'indefinite', 'starts_on' => $created->toDateString(), 'ends_on' => null, 'dates' => null];
            } else {
                $monday = $created->copy()->startOfWeek(Carbon::MONDAY);
                $dates = array_map(fn (int $d) => $monday->copy()->addDays($d - 1)->toDateString(), $weekdays);
                sort($dates);
                $values = ['kind' => 'dates', 'starts_on' => $dates[0], 'ends_on' => end($dates), 'dates' => json_encode($dates)];
            }

            DB::table('draw_blocks')->where('id', $block->id)->update($values);
        }

        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }

    public function down(): void
    {
        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->json('weekdays')->nullable()->after('reason');
        });

        // Solo se puede volver atrás lo que era "días de la semana": el resto queda como total
        foreach (DB::table('draw_blocks')->where('kind', 'dates')->get(['id', 'dates']) as $block) {
            $weekdays = array_values(array_unique(array_map(fn (string $d) => Carbon::parse($d)->dayOfWeek, json_decode($block->dates, true))));
            sort($weekdays);
            DB::table('draw_blocks')->where('id', $block->id)->update(['weekdays' => json_encode($weekdays)]);
        }

        Schema::table('draw_blocks', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'starts_on', 'ends_on']);
            $table->dropColumn(['kind', 'starts_on', 'ends_on', 'dates']);
        });
    }
};
