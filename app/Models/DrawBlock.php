<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de una placa en Sorteos: vigente mientras unblocked_at sea NULL.
 * Sin weekdays es total (no entra ningún día); con weekdays solo esos días
 * de la semana, todas las semanas (1 = lunes ... 6 = sábado).
 * Desbloquear completa unblocked_at/unblocked_by; nunca se borra.
 */
class DrawBlock extends Model
{
    use Auditable;

    /** Días que se pueden bloquear (no hay sorteo los domingos). */
    public const WEEKDAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];

    public const WEEKDAYS_SHORT = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];

    protected $auditModule = 'Sorteos - Bloqueos';

    protected $fillable = ['vehicle_id', 'reason', 'weekdays', 'blocked_by', 'unblocked_at', 'unblocked_by'];

    protected $casts = [
        'weekdays' => 'array',
        'unblocked_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('unblocked_at');
    }

    /** Bloqueos totales (todos los días). */
    public function scopeFull(Builder $q): Builder
    {
        return $q->whereNull('weekdays');
    }

    /** Bloqueos que aplican en $date: los totales y los de ese día de la semana. */
    public function scopeOnDate(Builder $q, string $date): Builder
    {
        $weekday = Carbon::parse($date)->dayOfWeek;

        return $q->where(fn (Builder $w) => $w->whereNull('weekdays')->orWhereJsonContains('weekdays', $weekday));
    }

    public function isFull(): bool
    {
        return empty($this->weekdays);
    }

    public function appliesOn(string $date): bool
    {
        return $this->isFull() || in_array(Carbon::parse($date)->dayOfWeek, $this->weekdays, true);
    }

    /** "Todos los días" o "Mar, Sáb". */
    public function weekdaysLabel(): string
    {
        return $this->isFull()
            ? 'Todos los días'
            : implode(', ', array_map(fn (int $d) => self::WEEKDAYS_SHORT[$d], $this->weekdays));
    }

    /** "martes y sábados", "lunes, miércoles y viernes" (para "bloqueada los …"). */
    public static function weekdaysPhrase(array $weekdays): string
    {
        $names = array_map(fn (int $d) => mb_strtolower(self::WEEKDAYS[$d]).($d === 6 ? 's' : ''), $weekdays);
        $last = array_pop($names);

        return $names ? implode(', ', $names).' y '.$last : (string) $last;
    }

    /**
     * IDs de los vehículos que no entran al sorteo de $date (bloqueo total o de ese día).
     *
     * @return array<int, int>
     */
    public static function blockedVehicleIdsOn(string $date): array
    {
        return static::active()->onDate($date)->pluck('vehicle_id')->map(fn ($id) => (int) $id)->all();
    }
}
