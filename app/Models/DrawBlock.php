<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bloqueo (sanción) de una placa en Sorteos: los días que cubre no entra al
 * sorteo ni a la simulación. Según `kind` cubre:
 *  - indefinite: desde starts_on hasta que lo desbloqueen (ends_on NULL)
 *  - month:      todo un mes (del día 1 al último)
 *  - range:      de starts_on a ends_on
 *  - dates:      solo los días de `dates` (starts_on/ends_on = el primero y el último)
 *
 * Dentro del periodo se pueden desbloquear días sueltos (exceptions), cada uno
 * con su motivo. Levantarlo completo guarda unblocked_at/unblocked_by y el
 * motivo (unblock_reason); nunca se borra, así cada placa tiene su historial
 * de sanciones (historyFor).
 */
class DrawBlock extends Model
{
    use Auditable;

    public const KIND_INDEFINITE = 'indefinite';

    public const KIND_MONTH = 'month';

    public const KIND_RANGE = 'range';

    public const KIND_DATES = 'dates';

    public const KIND_LABELS = [
        self::KIND_MONTH => 'Todo el mes',
        self::KIND_RANGE => 'Rango de fechas',
        self::KIND_DATES => 'Días específicos',
        self::KIND_INDEFINITE => 'Hasta desbloquearla',
    ];

    /** Color del motivo según el tipo, en la lista de Placas bloqueadas (sin rojo, que ya marca otras cosas). */
    public const KIND_COLORS = [
        self::KIND_MONTH => '#1d4ed8',
        self::KIND_RANGE => '#0e7490', // no verde: el verde es de los días desbloqueados
        self::KIND_DATES => '#7e22ce',
        self::KIND_INDEFINITE => '#b45309',
    ];

    public const STATUS_ACTIVE = 'vigente';

    public const STATUS_EXPIRED = 'cumplida';

    public const STATUS_LIFTED = 'levantada';

    private const WEEKDAYS_SHORT = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];

    protected $auditModule = 'Sorteos - Bloqueos';

    protected $fillable = ['vehicle_id', 'reason', 'kind', 'starts_on', 'ends_on', 'dates', 'blocked_by', 'unblocked_at', 'unblocked_by', 'unblock_reason'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'dates' => 'array',
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

    public function exceptions(): HasMany
    {
        return $this->hasMany(DrawBlockException::class)->orderBy('date');
    }

    /** Vigentes hoy: sin desbloquear y con su periodo sin terminar. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('unblocked_at')
            ->where(fn (Builder $w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', Carbon::today()->toDateString()));
    }

    /** Los que cubren $date (sin contar los días desbloqueados). */
    public function scopeOnDate(Builder $q, string $date): Builder
    {
        return $q->where('starts_on', '<=', $date)
            ->where(fn (Builder $w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->where(fn (Builder $w) => $w->whereNull('dates')->orWhereJsonContains('dates', $date))
            ->whereDoesntHave('exceptions', fn (Builder $e) => $e->where('date', $date));
    }

    /** Los que tocan algún día entre $from y $to. */
    public function scopeOverlapping(Builder $q, string $from, string $to): Builder
    {
        return $q->where('starts_on', '<=', $to)
            ->where(fn (Builder $w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $from));
    }

    public function isIndefinite(): bool
    {
        return $this->kind === self::KIND_INDEFINITE;
    }

    /** ¿$date cae en el periodo? (sin mirar días desbloqueados ni si está vigente) */
    public function coversDate(string $date): bool
    {
        return (! $this->starts_on || $date >= $this->starts_on->toDateString())
            && (! $this->ends_on || $date <= $this->ends_on->toDateString())
            && ($this->dates === null || in_array($date, $this->dates, true));
    }

    /**
     * Días desbloqueados dentro del periodo.
     *
     * @return array<int, string>
     */
    public function exceptionDates(): array
    {
        return $this->exceptions->map(fn (DrawBlockException $e) => $e->date->toDateString())->all();
    }

    /** ¿Bloquea $date? Cubre el día y ese día no se desbloqueó. */
    public function appliesOn(string $date): bool
    {
        return $this->coversDate($date) && ! in_array($date, $this->exceptionDates(), true);
    }

    /** "Todo noviembre 2026", "Del 09/11 al 14/11/2026", "Mié 04/11, Jue 05/11", "Desde el 31/10/2026, hasta desbloquearla". */
    public function periodLabel(): string
    {
        return match ($this->kind) {
            self::KIND_MONTH => 'Todo '.$this->starts_on->translatedFormat('F Y'),
            self::KIND_RANGE => 'Del '.$this->starts_on->format('d/m').' al '.$this->ends_on->format('d/m/Y'),
            self::KIND_DATES => implode(', ', array_map(fn (string $date) => self::dayLabel($date), $this->dates ?? [])),
            default => 'Desde el '.($this->starts_on?->format('d/m/Y') ?? '—').', hasta desbloquearla',
        };
    }

    /** Vigente, cumplida (ya terminó su periodo) o levantada (la desbloquearon). */
    public function status(): string
    {
        if ($this->unblocked_at) {
            return self::STATUS_LIFTED;
        }
        if ($this->ends_on && $this->ends_on->lt(Carbon::today())) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    /** Usuario que se muestra en bloqueos e historial: su username ("Licet"), no nombre y apellidos. */
    public static function userLabel(?User $user): string
    {
        return $user?->username ?: ($user?->name ?? '—');
    }

    /** "Sáb 07/11" */
    public static function dayLabel(string $date): string
    {
        $day = Carbon::parse($date);

        return self::WEEKDAYS_SHORT[$day->dayOfWeek].' '.$day->format('d/m');
    }

    /**
     * Historial de sanciones de una placa: todos sus bloqueos con quién los
     * puso y levantó y sus días desbloqueados, del más nuevo al más viejo.
     *
     * @return Collection<int, DrawBlock>
     */
    public static function historyFor(int $vehicleId): Collection
    {
        return static::where('vehicle_id', $vehicleId)
            ->with('blockedBy:id,name,username', 'unblockedBy:id,name,username', 'exceptions.createdBy:id,name,username')
            ->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    /**
     * IDs de los vehículos que no entran al sorteo de $date.
     *
     * @return array<int, int>
     */
    public static function blockedVehicleIdsOn(string $date): array
    {
        return static::active()->onDate($date)->pluck('vehicle_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
