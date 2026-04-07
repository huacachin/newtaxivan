<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtDayDetailImage extends Model
{
    protected $fillable = [
        'debt_day_detail_id',
        'image_path',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(DebtDayDetail::class, 'debt_day_detail_id');
    }
}
