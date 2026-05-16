<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\HasExpiringDocuments;

class Owner extends Model
{
    use Auditable, HasExpiringDocuments;
    protected $auditModule = 'Propietarios';

    protected $fillable = [
        'name',
        'document_type',
        'document_number',
        'document_expiration_date',
        'birthdate',
        'address',
        'district',
        'phone',
        'email',
        'image_path',
        'status',
    ];

    protected $casts = [
        'document_expiration_date' => 'date',
        'birthdate' => 'date',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class); // FK: vehicles.owner_id
    }

    public function expiringAlerts(): array
    {
        $alerts = [];
        $name = (string) ($this->name ?: '—');

        $alert = $this->buildExpirationAlert(
            $this->document_expiration_date,
            'owner', 'Propietario',
            (int) $this->id, $name,
            'DOC', 'documento', 'Documento'
        );
        if ($alert) $alerts[] = $alert;

        return $alerts;
    }
}
