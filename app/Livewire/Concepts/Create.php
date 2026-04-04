<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Component;

class Create extends Component
{
    public int    $sort_order = 0;
    public string $name   = '';
    public string $status = 'inactive';
    public string $type   = 'ingreso';

    public function mount(): void
    {
        $this->sort_order = (int) ((Concept::max('sort_order') ?? 0) + 1);
    }

    public function clear(): void
    {
        $this->name   = '';
        $this->status = 'inactive';
        $this->type   = 'ingreso';
        $this->sort_order = (int) ((Concept::max('sort_order') ?? 0) + 1);
        $this->resetErrorBag();
    }

    protected $rules = [
        'sort_order' => 'required|integer|min:0',
        'name'       => 'required|string|max:255',
        'status'     => 'required|string|max:255',
        'type'       => 'required|string|max:255',
    ];

    public function save(): void
    {
        try {
            $this->validate();

            Concept::create([
                'sort_order' => $this->sort_order,
                'name'       => $this->name,
                'status'     => $this->status,
                'type'       => $this->type,
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
