<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtDayDetail extends Model
{
    protected $table = 'debt_days_detail';

    // PK legado, no autoincremental
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'debt_days_id',
        'exonerated',
        'amortized',
        'detail',
        'user_id',
        'date',
    ];

    protected $casts = [
        'exonerated' => 'decimal:2',
        'amortized'  => 'decimal:2',
        'date'       => 'date',
    ];

    public function debtDay(): BelongsTo
    {
        return $this->belongsTo(DebtDay::class, 'debt_days_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
