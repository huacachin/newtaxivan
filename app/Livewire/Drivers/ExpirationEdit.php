<?php

namespace App\Livewire\Drivers;

use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExpirationEdit extends Component
{
    public Driver $driver;
    public int $id;
    public string $field;
    public ?string $value = null;

    protected const FIELDS = [
        'documento'       => ['column' => 'document_expiration_date',       'abbr' => 'DOC', 'label' => 'Documento'],
        'licencia'        => ['column' => 'license_revalidation_date',      'abbr' => 'LIC', 'label' => 'Licencia'],
        'educacion-vial'  => ['column' => 'road_education_expiration_date', 'abbr' => 'EV',  'label' => 'Educación Vial'],
        'credencial'      => ['column' => 'credential_expiration_date',     'abbr' => 'CR',  'label' => 'Credencial'],
    ];

    public function mount(int $id, string $field): void
    {
        abort_unless(array_key_exists($field, self::FIELDS), 404);

        $this->driver = Driver::findOrFail($id);
        $this->id     = $id;
        $this->field  = $field;

        $column      = self::FIELDS[$field]['column'];
        $current     = $this->driver->{$column};
        $this->value = $current ? Carbon::parse($current)->format('Y-m-d') : null;
    }

    public function rules(): array
    {
        return ['value' => 'required|date'];
    }

    protected $validationAttributes = ['value' => 'fecha'];

    public function save(): void
    {
        $this->validate();

        $column = self::FIELDS[$this->field]['column'];
        $this->driver->update([$column => $this->value]);

        Cache::forget('header_expiring_alerts');

        session()->flash(
            'driver_success',
            self::FIELDS[$this->field]['label'] . ' de ' . $this->driver->name . ' actualizado correctamente.'
        );

        $this->redirectRoute('settings.drivers.index');
    }

    public function getMetaProperty(): array
    {
        $meta = self::FIELDS[$this->field];
        $column = $meta['column'];
        $current = $this->driver->{$column};

        $days = null; $status = 'none'; $color = 'muted';

        if ($current) {
            $tz = config('app.timezone', 'America/Lima');
            $today = Carbon::now($tz)->startOfDay();
            $d = Carbon::parse($current, $tz)->startOfDay();

            if ($d->equalTo($today)) { $days = 0; $status = 'today'; $color = 'danger'; }
            elseif ($d->lessThan($today)) { $days = $d->diffInDays($today); $status = 'expired'; $color = 'danger'; }
            else {
                $days = $today->diffInDays($d);
                $status = $days <= 10 ? 'upcoming' : 'ok';
                $color = $days <= 5 ? 'danger' : ($days <= 10 ? 'warning' : 'muted');
            }
        }

        return [
            'abbr'    => $meta['abbr'],
            'label'   => $meta['label'],
            'current' => $current ? Carbon::parse($current)->format('d/m/Y') : null,
            'days'    => $days,
            'status'  => $status,
            'color'   => $color,
        ];
    }

    public function render()
    {
        return view('livewire.drivers.expiration-edit', ['meta' => $this->meta]);
    }
}
