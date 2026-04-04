<?php

namespace App\Livewire\Concepts;

use App\Models\Concept;
use Livewire\Component;

class Index extends Component
{

    public $search = '';
    public $type = "ingreso";
    public $name = "";
    public $status = "inactive";
    public $sort_order = 0;
    public $concepts;
    public $id = null;

    public function mount(){
        $term = trim($this->search);
        $this->concepts =  Concept::query()
            ->when($term !== '', fn ($q) =>
            $q->where('name', 'like', "%{$term}%")
            )
            ->orderBy('sort_order')
            ->get();
    }

    protected $rules = [
        "sort_order" => "required|integer|min:0",
        "name" => "required|string|max:255",
        "status" => "required|string|max:255",
        "type" => "required|string|max:255",
    ];

    public function updatedSearch(){
        $this->mount();
    }

    public function save(){

        $this->validate();
        Concept::create([
            "sort_order" => $this->sort_order,
            "name" => $this->name,
            "status" => $this->status,
            "type" => $this->type,
        ]);

        $this->reset(['sort_order','name','status','type']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalAddConcept"]);
        $this->dispatch('successAlert',["message" => "Concepto creado correctamente"]);

    }

    public function openAddModal(): void
    {
        $this->dispatch('url-open', ['url' => route('settings.concepts.create')]);
    }

    public function openEditWindow(int $id): void
    {
        $this->dispatch('url-open', ['url' => route('settings.concepts.edit', $id)]);
    }

    public function openEditModal($id){

        $concept = Concept::find($id);
        $this->id = $id;
        $this->sort_order = $concept->sort_order;
        $this->name = $concept->name;
        $this->status = $concept->status;
        $this->type = $concept->type;

        $this->dispatch('open-modal',["name" => "modalEditConcept","focus" => "sort_order"]);

    }

    public function update(){
        $this->validate();
        $concept = Concept::find($this->id);
        $concept->update([
            "sort_order" => $this->sort_order,
            "name" => $this->name,
            "status" => $this->status,
            "type" => $this->type,
        ]);

        $this->reset(['sort_order','name','status','type']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalEditConcept"]);
        $this->dispatch('successAlert',["message" => "Concepto actualizado correctamente"]);

    }

    public function export(): void
    {
        $this->dispatch('url-open', ['url' => route('exports.concepts', ['search' => $this->search])]);
    }

    public function render()
    {
        return view('livewire.concepts.index');
    }
}
