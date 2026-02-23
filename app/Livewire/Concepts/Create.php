<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Component;

class Create extends Component
{
    public string $code   = '';
    public string $name   = '';
    public string $status = 'inactive';
    public string $type   = 'ingreso';

    protected $rules = [
        'code'   => 'required|string|max:255',
        'name'   => 'required|string|max:255',
        'status' => 'required|string|max:255',
        'type'   => 'required|string|max:255',
    ];

    public function save(): void
    {
        try {
            $this->validate();

            Concept::create([
                'code'   => $this->code,
                'name'   => $this->name,
                'status' => $this->status,
                'type'   => $this->type,
            ]);

            session()->flash('concept_success', 'Concepto creado correctamente.');
            $this->redirectRoute('settings.concepts.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('concept_error', 'Error al crear: ' . $e->getMessage());
            $this->redirectRoute('settings.concepts.index');
        }
    }

    public function render()
    {
        return view('livewire.concepts.create');
    }
}
