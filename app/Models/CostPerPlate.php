<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostPerPlate extends Model
{
    use \App\Traits\Auditable;
    protected $auditModule = 'Costo por Placa';

    protected $fillable = ['vehicle_id','year','month','amount','order'];
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}
