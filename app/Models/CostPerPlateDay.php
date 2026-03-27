<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostPerPlateDay extends Model
{
    use \App\Traits\Auditable;
    protected $auditModule = 'Costo por Placa Día';

    protected $table = 'cost_per_plate_days';

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
        'year'   => 'integer',
        'month'  => 'integer'
    ];

    public function headquarter(){ return $this->belongsTo(\App\Models\Headquarter::class); }

    public function vehicle()
    {
        return $this->belongsTo(\App\Models\Vehicle::class)->withDefault();
    }
}
