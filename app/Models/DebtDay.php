<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtDay extends Model
{
    protected $table = 'debt_days';

    protected $fillable = [
        'vehicle_id',
        'legacy_plate',
        'is_support',
        // d1..d31
        'd1','d2','d3','d4','d5','d6','d7','d8','d9','d10',
        'd11','d12','d13','d14','d15','d16','d17','d18','d19','d20',
        'd21','d22','d23','d24','d25','d26','d27','d28','d29','d30','d31',
        'days',
        'total',
        'date',
        'exonerated',
        'detail_exonerated',
        'amortized',
        'condition',
        'days_late',
    ];

    protected $casts = [
        'date'        => 'date',
        'is_support'  => 'boolean',
        'exonerated'  => 'decimal:2',
        'total'       => 'decimal:2',
        'amortized'   => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function details()
    {
        return $this->hasMany(\App\Models\DebtDayDetail::class, 'debt_days_id');
    }
}
