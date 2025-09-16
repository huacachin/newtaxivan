<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'order',
        'plate',
        'headquarters',
        'entry_date',
        'termination_date',
        'class',
        'brand',
        'year',
        'model',
        'bodywork',
        'color',
        'type',
        'affiliated_company',
        'condition',
        'owner_id',
        'driver_id',
        'fuel',
        'soat_date',
        'certificate_date',
        'technical_review',
        'detail',
        'validity_status',
        'status',
    ];

    protected $casts = [
        'order'              => 'integer',
        'year'               => 'integer',
        'entry_date'         => 'date',
        'termination_date'   => 'date',
        'soat_date'          => 'date',
        'certificate_date'   => 'date',
        'technical_review'   => 'date',
    ];

    // Relaciones
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class)->withDefault();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withDefault();
    }

    public function costs() { return $this->hasMany(CostPerPlate::class); }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* ---------- Scopes / Helpers ---------- */

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeByCondition($q, ?string $cond)
    {
        if ($cond === null || $cond === '') return $q;
        return $q->where('condition', $cond);
    }

    public function scopeByPlate($q, string $term)
    {
        return $q->where('plate', 'like', '%'.strtoupper($term).'%');
    }

    public function setPlateAttribute($value): void
    {
        $s = strtoupper(trim((string)$value));
        // Limpia raros, mantiene guiones/espacios si los usas:
        $s = preg_replace('/[^A-Z0-9\- ]/', '', $s);
        $this->attributes['plate'] = $s;
    }

    /** Clave de comparación de placa (sin espacios/guiones) */
    public function plateKey(): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$this->plate));
    }

    public function debtDays()
    {
        return $this->hasMany(\App\Models\DebtDay::class);
    }

}
