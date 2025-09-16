<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departure extends Model
{
    protected $fillable = [
        'date',
        'hour',
        'vehicle_id',
        'times',
        'user_id',
        'headquarter_id',
        'price',
        'latitude',
        'longitude',
        'passenger','
        passage',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'datetime:H:i:s', // si prefieres string, quítalo
        'price' => 'decimal:2',
        'passage' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'times' => 'integer',
        'passenger' => 'integer',
    ];

    /* ---------- Relaciones ---------- */

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withDefault();
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class)->withDefault();
    }

    public function headquarter()
    {
        return $this->belongsTo(Headquarter::class)->withDefault();
    }

    /* ---------- Scopes ---------- */

    public function scopeBetweenDates($q, string $from, string $to)
    {
        return $q->whereBetween('date', [$from, $to]);
    }

    /** Solo apoyos (sin match de vehicle_id o marcado como tal) */
    public function scopeSupport($q)
    {
        return $q->where('is_support', true);
    }

    /** Excluye sedes específicas (Huachipa/Lima como en legacy) */
    public function scopeExcludeHQ($q, array $names = ['Huachipa','Lima'])
    {
        return $q->whereHas('headquarter', fn($hq) => $hq->whereNotIn('name', $names));
    }

}
