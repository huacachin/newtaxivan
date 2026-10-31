<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de un vehículo en un sorteo. Sin number ni band = no participó.
 * Sin Auditable a propósito: son ~45 filas por sorteo y la auditoría ya
 * registra el sorteo (Draw) que las crea.
 */
class DrawEntry extends Model
{
    public const BAND_LOW = 'low';

    public const BAND_MID = 'mid';

    public const BAND_HIGH = 'high';

    /** Orden del ciclo: tras bajo va medio, tras medio alto y tras alto otra vez bajo. */
    public const BANDS = [self::BAND_LOW, self::BAND_MID, self::BAND_HIGH];

    public const BAND_LABELS = [
        self::BAND_LOW => 'Bajo',
        self::BAND_MID => 'Medio',
        self::BAND_HIGH => 'Alto',
    ];

    protected $fillable = [
        'draw_id',
        'vehicle_id',
        'plate',
        'driver_name',
        'number',
        'band',
        'previous_band',
        'deviated',
    ];

    protected $casts = [
        'number' => 'integer',
        'deviated' => 'boolean',
    ];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Franja que sigue en el ciclo bajo -> medio -> alto -> bajo. */
    public static function nextBand(string $band): string
    {
        $i = array_search($band, self::BANDS, true);

        return self::BANDS[($i + 1) % count(self::BANDS)];
    }
}
