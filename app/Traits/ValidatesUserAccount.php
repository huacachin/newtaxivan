<?php

namespace App\Traits;

use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por los formularios de crear y editar usuario.
 * El componente debe tener las propiedades $document_type, $selectedRoleId y $roles.
 */
trait ValidatesUserAccount
{
    /**
     * @return array<int, mixed>
     */
    protected function documentTypeRules(): array
    {
        return ['required', 'string', Rule::in(['dni', 'ruc', 'ce'])];
    }

    /**
     * Formato del número según el tipo: DNI 8 dígitos, RUC 11 dígitos, CE 8 a 12 letras o números.
     *
     * @return array<int, string>
     */
    protected function documentNumberFormatRules(): array
    {
        return match (mb_strtolower((string) $this->document_type)) {
            'dni' => ['digits:8'],
            'ruc' => ['digits:11'],
            'ce' => ['regex:/^[A-Za-z0-9]{8,12}$/'],
            default => [],
        };
    }

    protected function selectedRoleName(): ?string
    {
        return collect($this->roles)->firstWhere('id', $this->selectedRoleId)?->name;
    }

    /**
     * Director y gerente reciben todas las sedes automáticamente al guardar.
     */
    protected function selectedRoleGetsAllHeadquarters(): bool
    {
        return in_array(mb_strtolower((string) $this->selectedRoleName()), ['director', 'gerente'], true);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'document_number.regex' => 'El CE debe tener entre 8 y 12 letras o números, sin espacios ni símbolos.',
            'selectedRoleId.required' => 'Selecciona un rol.',
            'selectedHeadquarters.required' => 'Marca al menos una sucursal.',
        ];
    }
}
