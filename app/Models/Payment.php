<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'id','serie','date_register','date_payment','vehicle_id','amount','type',
        'user_id','headquarter_id','hour','latitude','longitude',
        'legacy_plate','is_support',
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

    /** Por rango usando date_register (modo "Caja") */
    public function scopeBetweenRegister($q, string $from, string $to)
    {
        return $q->whereBetween('date_register', [$from, $to]);
    }

    /** Por rango usando date_payment (modo "Pago") */
    public function scopeBetweenPayment($q, string $from, string $to)
    {
        return $q->whereBetween('date_payment', [$from, $to]);
    }

    public function scopeByType($q, ?string $type)
    {
        if ($type === null || $type === '') return $q;
        return $q->where('type', $type);
    }

    public function scopeByHeadquarter($q, $headquarterId)
    {
        if ($headquarterId === null || $headquarterId === '') return $q;
        return $q->where('headquarter_id', $headquarterId);
    }

    /** Busca por placa (legacy o vehicle.plate) */
    public function scopeSearchPlate($q, string $term)
    {
        $plate = strtoupper($term);
        return $q->where(function($qq) use ($plate) {
            $qq->where('legacy_plate', 'like', '%'.$plate.'%')
                ->orWhereHas('vehicle', fn($v) => $v->where('plate', 'like', '%'.$plate.'%'));
        });
    }

    /** Busca por nombre de usuario (users.name) */
    public function scopeSearchUserName($q, string $term)
    {
        return $q->whereHas('user', fn($u) => $u->where('name', 'like', '%'.$term.'%'));
    }

    /** Serie */
    public function scopeSearchSerie($q, string $term)
    {
        return $q->where('serie', 'like', '%'.$term.'%');
    }
}
