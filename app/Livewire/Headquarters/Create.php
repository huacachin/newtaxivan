<?php

namespace App\Livewire\Headquarters;

use App\Models\Headquarter;
use Livewire\Component;

class Create extends Component
{
    public string $name   = '';
    public string $status = 'active';

    public function mount(): void
    {
        if (!auth()->user()?->hasAnyRole('director', 'gerente')) {
            abort(403);
        }
    }

    public function clear(): void
    {
        $this->name   = '';
        $this->status = 'active';
        $this->resetErrorBag();
    }

    protected $rules = [
        'name'   => 'required|string|max:150',
        'status' => 'required|in:active,inactive',
    ];

    public function save(): void
    {
        try {
            $this->validate();

            Headquarter::create([
                'name'   => $this->name,
                'status' => $this->status,
            ]);

            session()->flash('headquarter_success', 'Sucursal creada correctamente.');
            $this->redirectRoute('settings.headquarters.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('headquarter_error', 'Error al crear: ' . $e->getMessage());
            $this->redirectRoute('settings.headquarters.index');
        }
    }

    public function render()
    {
        return view('livewire.headquarters.create');
    }
}
