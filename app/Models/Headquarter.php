<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Headquarter extends Model
{
    protected $fillable = [
        'name',
        'status'
    ];
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departures()
    {
        return $this->hasMany(\App\Models\Departure::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }
}
