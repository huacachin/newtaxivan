<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Attributes\On;
use Livewire\Component;

class Edit extends Component
{
    public Concept $concept;
    public int $conceptId;

    public int    $sort_order = 0;
    public string $name   = '';
    public string $status = 'inactive';
    public string $type   = 'ingreso';

    public function mount(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente')) {
            abort(403);
        }

        $this->concept   = Concept::findOrFail($id);
        $this->conceptId = $id;

        $this->sort_order = (int)$this->concept->sort_order;
        $this->name       = (string)$this->concept->name;
        $this->status     = (string)$this->concept->status;
        $this->type       = (string)$this->concept->type;
    }

    protected $rules = [
        'sort_order' => 'required|integer|min:0',
        'name'       => 'required|string|max:255',
        'status'     => 'required|string|max:255',
        'type'       => 'required|string|max:255',
    ];

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente')) {
            abort(403);
        }

        Concept::findOrFail($id)->delete();
        session()->flash('concept_success', 'Concepto eliminado correctamente.');
        $this->redirectRoute('settings.concepts.index');
    }

    public function update(): void
    {
        try {
            $this->validate();

            $this->concept->update([
                'sort_order' => $this->sort_order,
                'name'       => $this->name,
                'status'     => $this->status,
                'type'       => $this->type,
            ]);

            session()->flash('concept_success', 'Concepto actualizado correctamente.');
            $this->redirectRoute('settings.concepts.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('concept_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('settings.concepts.index');
        }
    }

    public function render()
    {
        return view('livewire.concepts.edit');
    }
}
