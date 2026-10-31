<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feriado o día no laborable: Sorteos no sortea ese día.
 */
class Holiday extends Model
{
    use Auditable;

    protected $auditModule = 'Feriados';

    protected $fillable = ['date', 'description', 'created_by'];

    protected $casts = [
        'date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Fechas (Y-m-d) de feriados entre dos días, inclusive.
     *
     * @return array<int, string>
     */
    public static function datesBetween(string $from, string $to): array
    {
        return static::whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();
    }

    /**
     * Feriados nacionales del Perú para un año (Jueves y Viernes Santo
     * incluidos), en el formato ['Y-m-d' => descripción].
     *
     * @return array<string, string>
     */
    public static function nationalFor(int $year): array
    {
        $fixed = [
            '01-01' => 'Año Nuevo',
            '05-01' => 'Día del Trabajo',
            '06-07' => 'Batalla de Arica y Día de la Bandera',
            '06-29' => 'San Pedro y San Pablo',
            '07-23' => 'Día de la Fuerza Aérea del Perú',
            '07-28' => 'Fiestas Patrias',
            '07-29' => 'Fiestas Patrias',
            '08-06' => 'Batalla de Junín',
            '08-30' => 'Santa Rosa de Lima',
            '10-08' => 'Combate de Angamos',
            '11-01' => 'Día de Todos los Santos',
            '12-08' => 'Inmaculada Concepción',
            '12-09' => 'Batalla de Ayacucho',
            '12-25' => 'Navidad',
        ];

        $holidays = [];
        foreach ($fixed as $monthDay => $description) {
            $holidays["{$year}-{$monthDay}"] = $description;
        }

        $easter = self::easterSunday($year);
        $holidays[$easter->copy()->subDays(3)->toDateString()] = 'Jueves Santo';
        $holidays[$easter->copy()->subDays(2)->toDateString()] = 'Viernes Santo';

        ksort($holidays);

        return $holidays;
    }

    /**
     * Domingo de Pascua (algoritmo gregoriano anónimo), sin depender de la
     * extensión calendar de PHP.
     */
    public static function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
