<?php
// app/Livewire/Drivers/Edit.php
namespace App\Livewire\Drivers;

use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public Driver $driver;

    public $name;
    public $document_number;
    public $document_expiration_date;
    public $birthdate;
    public $address;
    public $district;
    public $email;
    public $phone;
    public $license;
    public $class;
    public $category;
    public $license_issue_date;
    public $license_revalidation_date;
    public $contract_start;
    public $contract_end;
    public $condition;
    public $score;
    public $cartilla_informativa;
    public $cartilla_informativa_expiration_date;
    public $cartilla_informativa_municipality;
    public $credential; // fecha
    public $credential_expiration_date;
    public $credential_municipality;
    public $details;
    public $image_file;
    public $existing_image;

    public $age;

    public function mount(int $id)
    {
        $this->driver = Driver::find($id);

        $this->name                         = $this->driver->name;
        $this->document_number              = $this->driver->document_number;
        $this->document_expiration_date     = optional($this->driver->document_expiration_date)?->format('Y-m-d');
        $this->birthdate                    = optional($this->driver->birthdate)?->format('Y-m-d');
        $this->age                          = $this->calculateAge($this->birthdate);
        $this->address                      = $this->driver->address;
        $this->district                     = $this->driver->district;
        $this->email                        = $this->driver->email;
        $this->phone                        = $this->driver->phone;
        $this->license                      = $this->driver->license;
        $this->class                        = $this->driver->class;
        $this->category                     = $this->driver->category;
        $this->license_issue_date           = optional($this->driver->license_issue_date)?->format('Y-m-d');
        $this->license_revalidation_date    = optional($this->driver->license_revalidation_date)?->format('Y-m-d');
        $this->contract_start               = optional($this->driver->contract_start)?->format('Y-m-d');
        $this->contract_end                 = optional($this->driver->contract_end)?->format('Y-m-d');
        $this->condition                    = $this->driver->condition;
        $this->score                        = $this->driver->score;
        $this->cartilla_informativa               = optional($this->driver->cartilla_informativa)?->format('Y-m-d');
        $this->cartilla_informativa_expiration_date = optional($this->driver->cartilla_informativa_expiration_date)?->format('Y-m-d');
        $this->cartilla_informativa_municipality  = $this->driver->cartilla_informativa_municipality;
        $this->credential                   = optional($this->driver->credential)?->format('Y-m-d');
        $this->credential_expiration_date   = optional($this->driver->credential_expiration_date)?->format('Y-m-d');
        $this->credential_municipality      = $this->driver->credential_municipality;
        $this->details                      = $this->driver->details;
        $this->existing_image               = $this->driver->image_path;
    }

    protected $validationAttributes = [
        'document_number' => 'documento de identidad',
    ];

    public function rules()
    {
        return [
            'name'                             => 'required|string|max:255',
            'document_number'                  => ['required','string','max:255', Rule::unique('drivers','document_number')->ignore($this->driver->id)],
            'document_expiration_date'         => 'nullable|date',
            'birthdate'                        => 'nullable|date',
            'address'                          => 'nullable|string|max:255',
            'district'                         => 'nullable|string|max:255',
            'email'                            => 'nullable|string|email|max:255',
            'phone'                            => 'nullable|string|max:255',
            'license'                          => 'nullable|string|max:255',
            'class'                            => 'nullable|string|max:255',
            'category'                         => 'nullable|string|max:255',
            'license_issue_date'               => 'nullable|date',
            'license_revalidation_date'        => 'nullable|date',
            'contract_start'                   => 'nullable|date',
            'contract_end'                     => 'nullable|date',
            'condition'                        => 'nullable|string|max:255',
            'score'                            => 'nullable|numeric|between:0,100',
            'cartilla_informativa'                   => 'nullable|date',
            'cartilla_informativa_expiration_date'   => 'nullable|date',
            'cartilla_informativa_municipality'      => 'nullable|string|max:255',
            'credential'                       => 'nullable|date',
            'credential_expiration_date'       => 'nullable|date',
            'credential_municipality'          => 'nullable|string|max:255',
            'details'                          => 'nullable|string|max:1000',
            'image_file'                       => 'nullable|image|max:3072',
        ];
    }

    protected function calculateAge(?string $date): ?int
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dob = Carbon::parse($date);
            return $dob->age;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function updatedBirthdate($value)
    {
        $this->age = $this->calculateAge($value);
    }

    /**
     * Helper genérico para saber si una fecha (Y-m-d) está vencida.
     */
    protected function isExpired(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        try {
            // Tus inputs están en formato Y-m-d
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable $e) {
            return false;
        }

        return $date->lt(today());
    }

    // ---- Accessors "computados" que usaremos en el Blade ----

    public function getDocumentExpirationExpiredProperty(): bool
    {
        return $this->isExpired($this->document_expiration_date);
    }

    public function getLicenseRevalidationExpiredProperty(): bool
    {
        return $this->isExpired($this->license_revalidation_date);
    }

    public function getCartillaInformativaExpirationExpiredProperty(): bool
    {
        return $this->isExpired($this->cartilla_informativa_expiration_date);
    }

    public function getCredentialExpirationExpiredProperty(): bool
    {
        return $this->isExpired($this->credential_expiration_date);
    }

    // ---------------------------------------------------------

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente','administrador')) {
            abort(403);
        }
        Driver::findOrFail($id)->update(['status' => 'inactive']);
        session()->flash('driver_success', 'Conductor eliminado correctamente.');
        $this->redirectRoute('settings.drivers.index');
    }

    public function update()
    {
        try {
            $this->validate();

            $payload = [
                "name"                           => $this->name,
                "document_number"                => $this->document_number,
                "document_expiration_date"       => $this->document_expiration_date,
                "birthdate"                      => $this->birthdate,
                "address"                        => $this->address,
                "district"                       => $this->district,
                "email"                          => $this->email,
                "phone"                          => $this->phone,
                "license"                        => $this->license,
                "class"                          => $this->class,
                "category"                       => $this->category,
                "license_issue_date"             => $this->license_issue_date,
                "license_revalidation_date"      => $this->license_revalidation_date,
                "contract_start"                 => $this->contract_start,
                "contract_end"                   => $this->contract_end,
                "condition"                      => $this->condition,
                "score"                          => $this->score ?? 0,
                "cartilla_informativa"                 => $this->cartilla_informativa,
                "cartilla_informativa_expiration_date" => $this->cartilla_informativa_expiration_date,
                "cartilla_informativa_municipality"    => $this->cartilla_informativa_municipality,
                "credential"                     => $this->credential,
                "credential_expiration_date"     => $this->credential_expiration_date,
                "credential_municipality"        => $this->credential_municipality,
                "details"                        => $this->details,
            ];

            if ($this->image_file) {
                $payload['image_path'] = $this->image_file->storePublicly('drivers', 'public');
            }

            $this->driver->update($payload);

            session()->flash('driver_success', 'Conductor actualizado correctamente.');
            $this->redirectRoute('settings.drivers.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('driver_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('settings.drivers.index');
        }
    }

    public function render()
    {
        return view('livewire.drivers.edit');
    }
}
