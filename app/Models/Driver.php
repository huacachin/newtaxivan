<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
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
        'road_education',
        'road_education_expiration_date',
        'road_education_municipality',
        'credential',
        'credential_expiration_date',
        'credential_municipality',
        'score',
    ];

    protected $casts = [
        'document_expiration_date' => 'date',
        'birthdate' => 'date',
        'license_issue_date' => 'date',
        'license_revalidation_date' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'road_education' => 'date',
        'road_education_expiration_date' => 'date',
        'credential' => 'date',
        'credential_expiration_date' => 'date',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
