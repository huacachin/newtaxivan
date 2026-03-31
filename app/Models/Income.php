<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Income extends Model
{
    use Auditable;
    protected $auditModule = 'Ingresos';

    protected $table = 'incomes';

    // Usamos id legacy, NO autoincremental
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'date',
        'reason',
        'detail',
        'image_path',
        'total',
        'user_id',
    ];

    protected $casts = [
        'date'  => 'date',
        'total' => 'decimal:2',
    ];

    public function getImageUrlAttribute(): string
    {
        if (!$this->image_path) {
            return asset('images/placeholder-income.png'); // colócalo en /public/images/
        }
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canBeEditedBy(User $user): bool
    {
        if ($user->hasRole('director')) return true;
        if (!$user->hasAnyRole(['administrador', 'gerente', 'controlador'])) return false;
        if ($this->user_id !== $user->id) return false;
        if (!$this->created_at || !$this->created_at->timezone(config('app.timezone'))->isToday()) return false;
        return true;
    }

    public function canBeDeletedBy(User $user): bool
    {
        if ($user->hasRole('director')) return true;
        if (!$user->hasAnyRole(['administrador', 'gerente', 'controlador'])) return false;
        if ($this->user_id !== $user->id) return false;
        if (!$this->created_at || !$this->created_at->timezone(config('app.timezone'))->isToday()) return false;
        return true;
    }
}
