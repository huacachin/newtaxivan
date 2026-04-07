<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebtDayDetail extends Model
{
    use \App\Traits\Auditable;
    protected $auditModule = 'Detalle Deuda';

    protected $table = 'debt_days_detail';

    public $incrementing = true;
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

    public function images(): HasMany
    {
        return $this->hasMany(DebtDayDetailImage::class, 'debt_day_detail_id');
    }
}
