<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Expense extends Model
{
    use Auditable;
    protected $auditModule = 'Egresos';

    protected $table = 'expenses';

    // Usamos el ID legacy como PK sin autoincremento
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'date',
        'reason',
        'kind',
        'detail',
        'image_path',
        'total',
        'user_id',
        'headquarter_id',
        'document_type',
        'in_charge',
    ];

    protected $casts = [
        'date'  => 'date',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function headquarter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Headquarter::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ExpenseImage::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image_path
            ? asset('storage/'.$this->image_path)
            : asset('images/placeholder-income.png');
    }

}
