<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Día desbloqueado dentro de un bloqueo de Sorteos: ese día la placa sí
 * entra al sorteo aunque el bloqueo lo cubra. Guarda motivo y quién lo hizo.
 */
class DrawBlockException extends Model
{
    use Auditable;

    protected $auditModule = 'Sorteos - Días desbloqueados';

    protected $fillable = ['draw_block_id', 'date', 'reason', 'created_by'];

    protected $casts = [
        'date' => 'date',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(DrawBlock::class, 'draw_block_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
