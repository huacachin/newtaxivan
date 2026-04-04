<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concept extends Model
{
    use \App\Traits\Auditable;
    protected $auditModule = 'Conceptos';

    protected $fillable = [
        'sort_order','name', 'type', 'status',
    ];

    protected $casts = [
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

}
