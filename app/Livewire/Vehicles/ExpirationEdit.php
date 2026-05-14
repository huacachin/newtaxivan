<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExpirationEdit extends Component
{
    public Vehicle $vehicle;
    public int $id;
    public string $field;
    public ?string $value = null;

    protected const FIELDS = [
        'soat'        => ['column' => 'soat_date',        'abbr' => 'ST', 'label' => 'SOAT'],
        'tecnica'     => ['column' => 'technical_review', 'abbr' => 'RT', 'label' => 'Revisión Técnica'],
        'certificado' => ['column' => 'certificate_date', 'abbr' => 'CD', 'label' => 'Certificado'],
    ];

    public function mount(int $id, string $field): void
    {
        abort_unless(array_key_exists($field, self::FIELDS), 404);

        $this->vehicle = Vehicle::findOrFail($id);
        $this->id      = $id;
        $this->field   = $field;

        $column      = self::FIELDS[$field]['column'];
        $current     = $this->vehicle->{$column};
        $this->value = $current ? Carbon::parse($current)->format('Y-m-d') : null;
    }

    public function rules(): array
    {
        return [
            'value' => 'required|date',
        ];
    }

    protected $validationAttributes = [
        'value' => 'fecha',
    ];

    public function save(): void
    {
        $this->validate();

        $column = self::FIELDS[$this->field]['column'];
        $this->vehicle->update([$column => $this->value]);

        Cache::forget('vehicle_expiring_alerts');

        session()->flash(
            'vehicle_success',
            self::FIELDS[$this->field]['label'] . ' de ' . $this->vehicle->plate . ' actualizado correctamente.'
        );

        $this->redirectRoute('settings.vehicles.index');
    }

    public function getMetaProperty(): array
    {
        $meta = self::FIELDS[$this->field];
        $column = $meta['column'];
        $current = $this->vehicle->{$column};

        $days = null;
        $status = 'none';
        $color = 'muted';

        if ($current) {
            $tz = config('app.timezone', 'America/Lima');
            $today = Carbon::now($tz)->startOfDay();
            $d = Carbon::parse($current, $tz)->startOfDay();

            if ($d->equalTo($today)) {
                $days = 0;
                $status = 'today';
                $color = 'danger';
            } elseif ($d->lessThan($today)) {
                $days = $d->diffInDays($today);
                $status = 'expired';
                $color = 'danger';
            } else {
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
        return view('livewire.vehicles.expiration-edit', [
            'meta' => $this->meta,
        ]);
    }
}
