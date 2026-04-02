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

}
