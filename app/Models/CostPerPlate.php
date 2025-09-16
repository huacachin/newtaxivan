<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostPerPlate extends Model
{
    protected $fillable = ['vehicle_id','year','month','amount','order'];
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}
