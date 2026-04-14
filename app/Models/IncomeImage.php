<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeImage extends Model
{
    protected $table = 'income_images';

    protected $fillable = [
        'income_id',
        'image_path',
    ];

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }
}
