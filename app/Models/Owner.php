<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    protected $fillable = [
        'name',
        'document_type',
        'document_number',
        'document_expiration_date',
        'birthdate',
        'address',
        'district',
        'phone',
        'email',
        'status',
    ];

    protected $casts = [
        'document_expiration_date' => 'date',
        'birthdate' => 'date',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class); // FK: vehicles.owner_id
    }

}
