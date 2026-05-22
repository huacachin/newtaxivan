<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Headquarter;
use App\Models\Owner;
use App\Models\Vehicle;
use Livewire\Component;

/**
 * Snapshot informativo de un registro eliminado, reusa el _form.blade.php
 * real del modulo para mostrar los datos tal cual aparecian en el edit
 * (read-only, vista nueva pestaña).
 *
 * Soporta hoy: Vehiculos, Conductores, Propietarios.
 * Otros modulos caen al fallback generico key/value (existing).
 */
class AuditSnapshot extends Component
{
    public ActivityLog $log;
    public array $data = [];
    public array $labels = [];
    public string $moduleLabel = '';
    public bool $isFullForm = false;

    // ---------------------------------------------------------
    // FK lists que los forms iteran. Si la FK del old_data no
    // existe en la lista actual, se inyecta un placeholder
    // "ELIMINADO #ID" como Eloquent fantasma.
    // ---------------------------------------------------------
    public $listOwners;
    public $listDrivers;
    public $listHeadquarters;

    // ---------------------------------------------------------
    // Fake Eloquent instances (con exists=true) para que el form
    // detecte $isEdit y se comporte como edicion.
    // ---------------------------------------------------------
    public ?Vehicle $vehicle = null;
    public ?Driver  $driver  = null;
    public ?Owner   $owner   = null;

    // ---------------------------------------------------------
    // Union de propiedades que los 3 forms usan via wire:model.
    // Quedan null por default; se hidratan en mount() segun el modulo.
    // ---------------------------------------------------------
    // Vehicles
    public $plate, $headquarter, $entry_date, $termination_date;
    public $class, $brand, $year, $model, $bodywork;
    public $color, $type, $affiliated_company, $condition;
    public $owner_id, $driver_id, $fuel, $soat_date, $technical_review, $certificate_date;
    public $detail, $sort_order, $seats = 0, $passengers = 0;

    // Drivers
    public $name, $document_number, $document_expiration_date, $birthdate;
    public $address, $district, $email, $phone;
    public $license, $category, $license_issue_date, $license_revalidation_date;
    public $contract_start, $contract_end;
    public $score, $cartilla_informativa, $cartilla_informativa_expiration_date;
    public $cartilla_informativa_municipality;
    public $credential, $credential_expiration_date, $credential_municipality;
    public $details, $age;

    // Owners
    public $document_type;

    // Multi-photos (placeholders vacios)
    public $new_images = [];
    public $image_files = [];
    public array $existing_images = [];
    public array $deleted_image_ids = [];

    protected const IGNORE_FIELDS = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'remember_token', 'email_verified_at',
    ];

    protected const FK_HINTS = [
        'owner_id' => 'Propietario', 'driver_id' => 'Conductor',
        'vehicle_id' => 'Vehículo', 'concept_id' => 'Concepto',
        'user_id' => 'Usuario', 'headquarter_id' => 'Sede',
    ];

    protected const COMMON_LABELS = [
        'image_path' => 'Foto', 'photo' => 'Foto', 'image' => 'Foto',
        'status' => 'Estado', 'sort_order' => 'Orden',
    ];

    protected const SUPPORTED_FULL_MODULES = ['Vehículos', 'Conductores', 'Propietarios'];

    public function mount(int $id): void
    {
        $this->log = ActivityLog::findOrFail($id);

        if ($this->log->action !== 'deleted' && !$this->log->isSoftDelete()) {
            abort(404);
        }

        $raw = is_array($this->log->old_data) ? $this->log->old_data : [];
        $this->data = array_diff_key($raw, array_flip(self::IGNORE_FIELDS));
        $this->moduleLabel = (string) $this->log->module;
        $this->labels = config('audit-field-labels.' . $this->moduleLabel, []);

        // Hidratacion completa con form replicado segun modulo
        $this->isFullForm = in_array($this->moduleLabel, self::SUPPORTED_FULL_MODULES, true);
        if (!$this->isFullForm) {
            return; // los demas modulos siguen con fallback key/value
        }

        match ($this->moduleLabel) {
            'Vehículos'    => $this->hydrateVehicle($raw),
            'Conductores'  => $this->hydrateDriver($raw),
            'Propietarios' => $this->hydrateOwner($raw),
        };
    }

    // ============================================================
    // Hidratadores por modulo
    // ============================================================

    protected function hydrateVehicle(array $d): void
    {
        // FIELD_PROP_MAP: 'headquarters' (DB) -> 'headquarter' (prop)
        $this->plate              = $d['plate']              ?? null;
        $this->headquarter        = $d['headquarters']       ?? null;
        $this->entry_date         = $this->toDate($d['entry_date'] ?? null);
        $this->termination_date   = $this->toDate($d['termination_date'] ?? null);
        $this->class              = $d['class']              ?? null;
        $this->brand              = $d['brand']              ?? null;
        $this->year               = $d['year']               ?? null;
        $this->model              = $d['model']              ?? null;
        $this->bodywork           = $d['bodywork']           ?? null;
        $this->color              = $d['color']              ?? null;
        $this->type               = $d['type']               ?? null;
        $this->affiliated_company = $d['affiliated_company'] ?? null;
        $this->condition          = $d['condition']          ?? null;
        $this->owner_id           = $d['owner_id']           ?? null;
        $this->driver_id          = $d['driver_id']          ?? null;
        $this->fuel               = $d['fuel']               ?? null;
        $this->soat_date          = $this->toDate($d['soat_date'] ?? null);
        $this->certificate_date   = $this->toDate($d['certificate_date'] ?? null);
        $this->technical_review   = $this->toDate($d['technical_review'] ?? null);
        $this->detail             = $d['detail']             ?? null;
        $this->sort_order         = $d['sort_order']         ?? null;
        $this->seats              = $d['seats']              ?? 0;
        $this->passengers         = $d['passengers']         ?? 0;

        // FK lists + placeholders ELIMINADO #ID si la FK no existe
        $this->listOwners       = $this->buildList(Owner::class, $this->owner_id);
        $this->listDrivers      = $this->buildList(Driver::class, $this->driver_id);
        $this->listHeadquarters = Headquarter::all();

        // Fake Vehicle con exists=true para que $isEdit en el form sea true
        $this->vehicle = $this->fakeModel(Vehicle::class, $d);
    }

    protected function hydrateDriver(array $d): void
    {
        $this->name                                = $d['name']                                ?? null;
        $this->document_number                     = $d['document_number']                     ?? null;
        $this->document_expiration_date            = $this->toDate($d['document_expiration_date'] ?? null);
        $this->birthdate                           = $this->toDate($d['birthdate']             ?? null);
        $this->address                             = $d['address']                             ?? null;
        $this->district                            = $d['district']                            ?? null;
        $this->email                               = $d['email']                               ?? null;
        $this->phone                               = $d['phone']                               ?? null;
        $this->license                             = $d['license']                             ?? null;
        $this->class                               = $d['class']                               ?? null;
        $this->category                            = $d['category']                            ?? null;
        $this->license_issue_date                  = $this->toDate($d['license_issue_date']    ?? null);
        $this->license_revalidation_date           = $this->toDate($d['license_revalidation_date'] ?? null);
        $this->contract_start                      = $this->toDate($d['contract_start']        ?? null);
        $this->contract_end                        = $this->toDate($d['contract_end']          ?? null);
        $this->condition                           = $d['condition']                           ?? null;
        $this->score                               = $d['score']                               ?? null;
        $this->cartilla_informativa                = $this->toDate($d['cartilla_informativa']  ?? null);
        $this->cartilla_informativa_expiration_date= $this->toDate($d['cartilla_informativa_expiration_date'] ?? null);
        $this->cartilla_informativa_municipality   = $d['cartilla_informativa_municipality']   ?? null;
        $this->credential                          = $this->toDate($d['credential']            ?? null);
        $this->credential_expiration_date          = $this->toDate($d['credential_expiration_date'] ?? null);
        $this->credential_municipality             = $d['credential_municipality']             ?? null;
        $this->details                             = $d['details']                             ?? null;

        $this->age = $this->birthdate ? \Carbon\Carbon::parse($this->birthdate)->age : null;

        $this->driver = $this->fakeModel(Driver::class, $d);
    }

    protected function hydrateOwner(array $d): void
    {
        $this->name                     = $d['name']                     ?? null;
        $this->document_type            = $d['document_type']            ?? null;
        $this->document_number          = $d['document_number']          ?? null;
        $this->document_expiration_date = $this->toDate($d['document_expiration_date'] ?? null);
        $this->birthdate                = $this->toDate($d['birthdate'] ?? null);
        $this->address                  = $d['address']                  ?? null;
        $this->district                 = $d['district']                 ?? null;
        $this->phone                    = $d['phone']                    ?? null;
        $this->email                    = $d['email']                    ?? null;

        $this->owner = $this->fakeModel(Owner::class, $d);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /** Devuelve una coleccion de la tabla; si la FK del old_data no existe ahi, prepende un placeholder ELIMINADO #ID. */
    protected function buildList(string $modelClass, $fkValue)
    {
        $list = $modelClass::all();
        if (!$fkValue) return $list;
        if ($list->contains('id', (int) $fkValue)) return $list;

        $placeholder = new $modelClass();
        $placeholder->forceFill(['id' => (int) $fkValue, 'name' => "ELIMINADO #{$fkValue}"]);
        $placeholder->exists = true;
        // Prepend al inicio
        return collect([$placeholder])->merge($list);
    }

    /** Construye un Eloquent fantasma con exists=true para satisfacer $isEdit en los forms. */
    protected function fakeModel(string $modelClass, array $attrs)
    {
        $m = new $modelClass();
        // forceFill bypasea fillable; queremos cargar todo lo que viene del audit
        $m->forceFill($attrs);
        $m->exists = true;
        return $m;
    }

    /** Normaliza fechas a Y-m-d (los forms con datepicker esperan eso). */
    protected function toDate($v): ?string
    {
        if (!$v) return null;
        try {
            return \Carbon\Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return is_string($v) ? $v : null;
        }
    }

    // ============================================================
    // Helpers de display para el fallback key/value
    // ============================================================

    public function labelFor(string $field): string
    {
        if (isset($this->labels[$field]))         return $this->labels[$field];
        if (isset(self::COMMON_LABELS[$field]))   return self::COMMON_LABELS[$field];
        if (isset(self::FK_HINTS[$field]))        return self::FK_HINTS[$field];
        return ucfirst(str_replace('_', ' ', $field));
    }

    public function valueFor(string $field, $value): string
    {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Sí' : 'No';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $str = (string) $value;
        if (str_ends_with($field, '_id') && ctype_digit($str)) return "ELIMINADO #{$str}";
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            try { return \Carbon\Carbon::parse($str)->format('d/m/Y'); } catch (\Throwable $e) {}
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $str)) {
            try { return \Carbon\Carbon::parse($str)->format('d/m/Y H:i'); } catch (\Throwable $e) {}
        }
        return $str;
    }

    public function isLongText(string $field, $value): bool
    {
        if (!is_string($value)) return false;
        if (mb_strlen($value) > 80) return true;
        return in_array($field, ['detail', 'details', 'observations', 'notes', 'address', 'reason'], true);
    }

    // ============================================================
    // Computed properties que algunos forms (Drivers/Owners) leen
    // ============================================================
    public function getDocumentExpirationExpiredProperty(): bool        { return false; }
    public function getLicenseRevalidationExpiredProperty(): bool       { return false; }
    public function getCartillaInformativaExpirationExpiredProperty(): bool { return false; }
    public function getCredentialExpirationExpiredProperty(): bool      { return false; }

    // ============================================================
    // Stubs no-op para que wire:click en el form no rompa Livewire.
    // El <fieldset disabled> impide que se invoquen, pero si Livewire
    // intenta validar la existencia del metodo, debe estar definido.
    // ============================================================
    public function updatedNewImages(): void {}
    public function removeNewImage($i = null): void {}
    public function removeExistingImage($i = null): void {}
    public function restoreExistingImage($i = null): void {}
    public function update(): void {}
    public function questionDelete($id = null): void {}
    public function checkDocumentNumber(): void {}

    public function render()
    {
        return view('livewire.audit-snapshot');
    }
}
