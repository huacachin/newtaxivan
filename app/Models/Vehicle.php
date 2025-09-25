<?php

namespace App\Models;

use Carbon\Carbon;
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

    protected $appends = ['badges'];

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

    public function getBadgesAttribute(): array
    {
        $now    = Carbon::now();
        $badges = [];

        $add = function ($date, string $abbr, string $label) use (&$badges, $now) {
            if (!$date) return;

            $date = $date instanceof Carbon ? $date : Carbon::parse($date);
            $days = $now->diffInDays($date, false); // negativo si ya venció

            // Mostrar solo si faltan 0–10 días
            if ($days < 0 || $days > 10) return;

            $badges[] = [
                'abbr'  => $abbr,
                'title' => "{$label} vence en {$days} día(s)",
                'class' => $days <= 5 ? 'bg-danger' : 'bg-warning',
            ];
        };

        $add($this->soat_date,        'SD', 'SOAT');
        $add($this->technical_review, 'RT', 'Revisión Técnica');
        $add($this->certificate_date, 'CD', 'Certificado');

        return $badges;
    }

    public function expiringAlerts(): array
    {
        $now = \Carbon\Carbon::now();
        $alerts = [];

        $add = function ($date, string $abbr, string $label) use (&$alerts, $now) {
            if (!$date) return;

            // diferencia en minutos, permitiendo negativos
            $mins = $now->diffInMinutes($date, false);

            // vencidos no se muestran (si quieres mostrarlos, quita este return)
            if ($mins < 0) return;

            // días restantes redondeados hacia arriba (0.1 día => 1 día)
            $days = (int) ceil($mins / 1440);

            if ($days > 10) return; // solo 0–10 días

            $alerts[] = [
                'plate'    => $this->plate,
                'abbr'     => $abbr,                          // SD|RT|CD
                'label'    => $label,                         // SOAT|Revisión Técnica|Certificado
                'days'     => $days,                          // ENTERO
                'due_date' => $date->format('Y-m-d'),
                'color'    => $days <= 5 ? 'danger' : 'warning',
            ];
        };

        $add($this->soat_date,        'SD', 'SOAT');
        $add($this->technical_review, 'RT', 'Revisión Técnica');
        $add($this->certificate_date, 'CD', 'Certificado');

        return $alerts;
    }

}
