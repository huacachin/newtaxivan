<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de una placa en Sorteos: vigente mientras unblocked_at sea NULL.
 * Desbloquear completa unblocked_at/unblocked_by; nunca se borra.
 */
class DrawBlock extends Model
{
    use Auditable;

    protected $auditModule = 'Sorteos - Bloqueos';

    protected $fillable = ['vehicle_id', 'reason', 'blocked_by', 'unblocked_at', 'unblocked_by'];

    protected $casts = [
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

    /**
     * IDs de los vehículos con bloqueo vigente.
     *
     * @return array<int, int>
     */
    public static function blockedVehicleIds(): array
    {
        return static::active()->pluck('vehicle_id')->map(fn ($id) => (int) $id)->all();
    }
}
