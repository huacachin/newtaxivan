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
    public $code = "";
    public $concepts;
    public $id = null;

    public function mount(){
        $term = trim($this->search);
        $this->concepts =  Concept::query()
            ->when($term !== '', fn ($q) =>
            $q->where('name', 'like', "%{$term}%")
            )
            ->orderBy('name')
            ->get();
    }

    protected $rules = [
        "code" => "required|string|max:255",
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
            "code" => $this->code,
            "name" => $this->name,
            "status" => $this->status,
            "type" => $this->type,
        ]);

        $this->reset(['code','name','status','type']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalAddConcept"]);
        $this->dispatch('successAlert',["message" => "Concepto creado correctamente"]);

    }

    public function openAddModal(){
        $this->reset(['code','name','status','type']);
        $this->dispatch('open-modal',["name" => "modalAddConcept","focus" => "code"]);
    }

    public function openEditModal($id){

        $concept = Concept::find($id);
        $this->id = $id;
        $this->code = $concept->code;
        $this->name = $concept->name;
        $this->status = $concept->status;
        $this->type = $concept->type;

        $this->dispatch('open-modal',["name" => "modalEditConcept","focus" => "code"]);

    }

    public function update(){
        $this->validate();
        $concept = Concept::find($this->id);
        $concept->update([
            "code" => $this->code,
            "name" => $this->name,
            "status" => $this->status,
            "type" => $this->type,
        ]);

        $this->reset(['code','name','status','type']);
        $this->mount();
        $this->dispatch('modal-close',["name" => "modalEditConcept"]);
        $this->dispatch('successAlert',["message" => "Concepto actualizado correctamente"]);

    }

    public function render()
    {
        return view('livewire.concepts.index');
    }
}
