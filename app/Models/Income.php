<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    protected $table = 'incomes';

    // Usamos id legacy, NO autoincremental
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'date',
        'reason',
        'detail',
        'total',
        'user_id',
    ];

    protected $casts = [
        'date'  => 'date',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
