<?php

namespace App\Livewire\Headquarters;

use App\Models\Headquarter;
use Livewire\Attributes\On;
use Livewire\Component;

class Edit extends Component
{
    public Headquarter $headquarter;
    public int $headquarterId;

    public string $name   = '';
    public string $status = 'active';

    public function mount(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director', 'gerente')) {
            abort(403);
        }

        $this->headquarter   = Headquarter::findOrFail($id);
        $this->headquarterId = $id;

        $this->name   = (string) $this->headquarter->name;
        $this->status = (string) $this->headquarter->status;
    }

    protected $rules = [
        'name'   => 'required|string|max:150',
        'status' => 'required|in:active,inactive',
    ];

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director', 'gerente')) {
            abort(403);
        }

        Headquarter::findOrFail($id)->delete();
        session()->flash('headquarter_success', 'Sucursal eliminada correctamente.');
        $this->redirectRoute('settings.headquarters.index');
    }

    public function update(): void
    {
        try {
            $this->validate();

            $this->headquarter->update([
                'name'   => $this->name,
                'status' => $this->status,
            ]);

            session()->flash('headquarter_success', 'Sucursal actualizada correctamente.');
            $this->redirectRoute('settings.headquarters.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('headquarter_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('settings.headquarters.index');
        }
    }

    public function render()
    {
        return view('livewire.headquarters.edit');
    }
}
