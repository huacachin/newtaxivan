<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $table = 'expenses';

    // Usamos el ID legacy como PK sin autoincremento
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'date',
        'reason',
        'detail',
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
}
