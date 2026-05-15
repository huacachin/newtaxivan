<?php

namespace App\Livewire\Owners;

use App\Models\Owner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExpirationEdit extends Component
{
    public Owner $owner;
    public int $id;
    public string $field;
    public ?string $value = null;

    protected const FIELDS = [
        'documento' => ['column' => 'document_expiration_date', 'abbr' => 'DOC', 'label' => 'Documento'],
    ];

    public function mount(int $id, string $field): void
    {
        abort_unless(array_key_exists($field, self::FIELDS), 404);

        $this->owner = Owner::findOrFail($id);
        $this->id    = $id;
        $this->field = $field;

        $column      = self::FIELDS[$field]['column'];
        $current     = $this->owner->{$column};
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
        $this->owner->update([$column => $this->value]);

        Cache::forget('header_expiring_alerts');

        session()->flash(
            'owner_success',
            self::FIELDS[$this->field]['label'] . ' de ' . $this->owner->name . ' actualizado correctamente.'
        );

        $this->redirectRoute('settings.owners.index');
    }

    public function getMetaProperty(): array
    {
        $meta = self::FIELDS[$this->field];
        $column = $meta['column'];
        $current = $this->owner->{$column};

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
        return view('livewire.owners.expiration-edit', ['meta' => $this->meta]);
    }
}
