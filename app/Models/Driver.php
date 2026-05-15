<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\HasExpiringDocuments;

class Driver extends Model
{
    use Auditable, HasExpiringDocuments;
    protected $auditModule = 'Conductores';

    protected $fillable = [
        'name',
        'document_number',
        'document_expiration_date',
        'birthdate',
        'email',
        'district',
        'address',
        'phone',
        'license',
        'class',
        'category',
        'license_issue_date',
        'license_revalidation_date',
        'contract_start',
        'contract_end',
        'condition',
        'status',
        'cartilla_informativa',
        'cartilla_informativa_expiration_date',
        'cartilla_informativa_municipality',
        'credential',
        'credential_expiration_date',
        'credential_municipality',
        'score',
        'details',
        'image_path',
    ];

    protected $casts = [
        'document_expiration_date' => 'date',
        'birthdate' => 'date',
        'license_issue_date' => 'date',
        'license_revalidation_date' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'cartilla_informativa' => 'date',
        'cartilla_informativa_expiration_date' => 'date',
        'credential' => 'date',
        'credential_expiration_date' => 'date',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function expiringAlerts(): array
    {
        $alerts = [];
        $id   = (int) $this->id;
        $name = (string) ($this->name ?: '—');

        $rows = [
            [$this->document_expiration_date,        'DOC', 'documento',       'Documento'],
            [$this->license_revalidation_date,       'LIC', 'licencia',        'Licencia'],
            [$this->cartilla_informativa_expiration_date,  'CI',  'cartilla-informativa',  'Cartilla Informativa'],
            [$this->credential_expiration_date,      'CR',  'credencial',      'Credencial'],
        ];

        foreach ($rows as [$date, $abbr, $slug, $label]) {
            $a = $this->buildExpirationAlert($date, 'driver', 'Conductor', $id, $name, $abbr, $slug, $label);
            if ($a) $alerts[] = $a;
        }

        return $alerts;
    }
}
