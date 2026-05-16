<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Vehicle extends Model
{
    use Auditable;
    protected $auditModule = 'Vehículos';

    protected $fillable = [
        'sort_order',
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
        'seats',
        'passengers'
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

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class);
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
        $badges = [];

        $add = function ($date, string $abbrType, string $label) use (&$badges) {
            if (!$date) return;

            $days = $this->dayDelta($date);
            if ($days === null) return;

            $due = $date instanceof \Carbon\Carbon
                ? $date->toDateString()
                : \Carbon\Carbon::parse($date)->toDateString();

            if ($days < 0) {
                $badges[] = [
                    'abbr'  => $abbrType, // ST | RT | CD
                    'title' => "{$label} venció hace " . abs($days) . " día(s) ({$due})",
                    'class' => 'bg-danger',
                ];
                return;
            }

            if ($days === 0) {
                $badges[] = [
                    'abbr'  => $abbrType,
                    'title' => "{$label} vence hoy ({$due})",
                    'class' => 'bg-danger',
                ];
                return;
            }

            if ($days <= 10) {
                $badges[] = [
                    'abbr'  => $abbrType,
                    'title' => "{$label} vence en {$days} día(s) ({$due})",
                    'class' => $days <= 5 ? 'bg-danger' : 'bg-warning',
                ];
            }
        };

        $add($this->soat_date,        'ST', 'SOAT');
        $add($this->technical_review, 'RT', 'Revisión Técnica');
        $add($this->certificate_date, 'CD', 'Certificado');

        return $badges;
    }

    public function expiringAlerts(): array
    {
        $alerts = [];
        $id = $this->id;
        $plate = $this->plate;

        $add = function ($date, string $abbrType, string $label, string $fieldSlug) use (&$alerts, $id, $plate) {
            if (!$date) return;

            $days = $this->dayDelta($date);
            if ($days === null) return;

            $due = $date instanceof \Carbon\Carbon
                ? $date->toDateString()
                : \Carbon\Carbon::parse($date)->toDateString();

            $base = [
                'id'         => $id,
                'kind'       => 'vehicle',
                'kind_label' => 'Vehículo',
                'subject'    => $plate,
                'plate'      => $plate,        // mantener compat
                'abbr'       => $abbrType,     // ST | RT | CD
                'field'      => $fieldSlug,    // soat | tecnica | certificado
                'label'      => $label,
            ];

            if ($days < 0) {
                $alerts[] = $base + [
                    'days'     => abs($days),
                    'due_date' => $due,
                    'color'    => 'danger',
                    'status'   => 'expired',
                    'message'  => "{$label} venció hace " . abs($days) . " día(s)",
                ];
                return;
            }

            if ($days === 0) {
                $alerts[] = $base + [
                    'days'     => 0,
                    'due_date' => $due,
                    'color'    => 'danger',
                    'status'   => 'today',
                    'message'  => "{$label} vence hoy",
                ];
                return;
            }

            if ($days <= 10) {
                $alerts[] = $base + [
                    'days'     => $days,
                    'due_date' => $due,
                    'color'    => $days <= 5 ? 'danger' : 'warning',
                    'status'   => 'upcoming',
                    'message'  => "{$label} vence en {$days} día(s)",
                ];
            }
        };

        $add($this->soat_date,        'ST', 'SOAT',             'soat');
        $add($this->technical_review, 'RT', 'Revisión Técnica', 'tecnica');
        $add($this->certificate_date, 'CD', 'Certificado',      'certificado');

        return $alerts;
    }




    /**
     * Diferencia firmada en días entre HOY y $date.
     * <0 = vencido, 0 = hoy, >0 = faltan N días.
     */
    protected function dayDelta($date): ?int
    {
        if (!$date) return null;

        $tz = config('app.timezone', 'UTC');

        // Normaliza ambos al inicio de día en la TZ de la app
        $d = $date instanceof \Carbon\Carbon
            ? $date->copy()->timezone($tz)->startOfDay()
            : \Carbon\Carbon::parse($date, $tz)->startOfDay();

        $today = \Carbon\Carbon::now($tz)->startOfDay();

        if ($d->equalTo($today)) {
            return 0;
        }

        // IMPORTANTE: calculamos el signo manualmente
        if ($d->lessThan($today)) {
            // Vencido: días negativos
            return -$d->diffInDays($today); // diffInDays SIEMPRE positivo; le ponemos el signo
        }

        // Futuro: días positivos
        return $today->diffInDays($d);
    }



}
