<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Sanción de una placa en Sorteos (una falta), con motivo y foto opcional.
 * Cada SANCTIONS_TO_SUSPEND sanciones acumuladas (sin suspensión todavía) la
 * placa se suspende sola (DrawService::sanction); las que la generaron quedan
 * con draw_block_id. Se ve en el historial de la placa.
 */
class DrawSanction extends Model
{
    use Auditable;

    /** Sanciones acumuladas que generan una suspensión. */
    public const SANCTIONS_TO_SUSPEND = 2;

    /** Días de sorteo que dura la suspensión. */
    public const SUSPENSION_DAYS = 3;

    /** Nombre y color del motivo en el historial (sin rojo, como los bloqueos). */
    public const LABEL = 'Sanción';

    public const COLOR = '#475569';

    protected $auditModule = 'Sorteos - Sanciones';

    protected $fillable = ['vehicle_id', 'reason', 'photo_path', 'draw_block_id', 'created_by'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Suspensión que generó (al juntarse con otra). */
    public function block(): BelongsTo
    {
        return $this->belongsTo(DrawBlock::class, 'draw_block_id');
    }

    /** Acumuladas: todavía no generaron una suspensión. */
    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('draw_block_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Sanciones de una placa, de la más nueva a la más vieja.
     *
     * @return Collection<int, DrawSanction>
     */
    public static function historyFor(int $vehicleId): Collection
    {
        return static::where('vehicle_id', $vehicleId)->with('createdBy:id,name,username', 'block')
            ->orderByDesc('created_at')->orderByDesc('id')->get();
    }
}
