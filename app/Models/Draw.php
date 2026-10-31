<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Sorteo de un día hábil: reparte los números 1..N entre los vehículos que
 * participan. Se anula (nunca se borra) y solo el último vigente.
 */
class Draw extends Model
{
    use Auditable;

    protected $auditModule = 'Sorteos';

    /** Sin 0/O ni 1/I/L para que se pueda dictar sin confusiones. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 8;

    protected $fillable = [
        'code',
        'date',
        'active_date',
        'participants',
        'deviations',
        'repeated_numbers',
        'drawn_by',
        'annulled_at',
        'annulled_by',
        'annul_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'active_date' => 'date',
        'participants' => 'integer',
        'deviations' => 'integer',
        'repeated_numbers' => 'integer',
        'annulled_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(DrawEntry::class);
    }

    public function drawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('annulled_at');
    }

    public function isAnnulled(): bool
    {
        return $this->annulled_at !== null;
    }

    /** Código del sorteo que identifica su PDF, p. ej. "K7QM4XTB" (31^8 combinaciones). */
    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** "Orden de salida · Sábado 31/10" */
    public static function orderTitleFor(string $date): string
    {
        $day = Carbon::parse($date);

        return 'Orden de salida · '.Str::ucfirst($day->translatedFormat('l')).' '.$day->format('d/m');
    }

    /** "Viernes 30 de octubre" */
    public static function dayTitleFor(string $date): string
    {
        return Str::ucfirst(Carbon::parse($date)->translatedFormat('l j \d\e F'));
    }
}
