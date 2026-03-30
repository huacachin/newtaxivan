<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Headquarter extends Model
{
    use \App\Traits\Auditable;
    protected $auditModule = 'Sucursales';

    protected $fillable = [
        'name',
        'sort_order',
        'status'
    ];
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function activeUsers()
    {
        return $this->belongsToMany(User::class)->where('users.status', 'active');
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
