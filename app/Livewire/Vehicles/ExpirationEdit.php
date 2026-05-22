<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExpirationEdit extends Component
{
    use WithFileUploads;

    public Vehicle $vehicle;
    public int $id;
    public string $field;
    public ?string $value = null;

    public $new_images = [];
    public $image_files = [];

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
        $column  = self::FIELDS[$this->field]['column'];
        $current = $this->vehicle->{$column};

        $valueRule = ['required', 'date'];
        if ($current) {
            $valueRule[] = 'after:' . Carbon::parse($current)->format('Y-m-d');
        }

        return [
            'value'        => $valueRule,
            'new_images'   => 'nullable|array|max:10',
            'new_images.*' => 'image|max:5120',
        ];
    }

    protected $validationAttributes = [
        'value' => 'fecha',
    ];

    public function messages(): array
    {
        $column  = self::FIELDS[$this->field]['column'];
        $current = $this->vehicle->{$column};

        $afterMsg = $current
            ? 'La nueva fecha debe ser posterior a ' . Carbon::parse($current)->format('d/m/Y') . ' (la actual). No puede ser igual.'
            : 'La fecha es obligatoria.';

        return [
            'value.required' => 'La fecha es obligatoria.',
            'value.date'     => 'La fecha no es válida.',
            'value.after'    => $afterMsg,
        ];
    }

    public function updatedNewImages(): void
    {
        foreach ($this->new_images as $file) {
            $this->image_files[] = $file;
        }
        $this->new_images = [];
    }

    public function removeNewImage(int $index): void
    {
        array_splice($this->image_files, $index, 1);
    }

    public function save(): void
    {
        $this->validate();

        $column = self::FIELDS[$this->field]['column'];

        DB::transaction(function () use ($column) {
            $this->vehicle->update([$column => $this->value]);

            foreach ($this->image_files as $file) {
                $path = $file->storePublicly('vehicles', 'public');
                VehicleImage::create([
                    'vehicle_id' => $this->vehicle->id,
                    'image_path' => $path,
                ]);
            }
        });

        Cache::forget('header_expiring_alerts');

        $this->dispatch('successAlert', [
            'message' => self::FIELDS[$this->field]['label'] . ' de ' . $this->vehicle->plate . ' actualizado correctamente.'
        ]);

        $this->dispatch('expiration-saved');
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
