<?php

namespace App\Livewire\Dsp;

use Livewire\Component;

class Index extends Component
{

    public $type = "";
    public $user = "";
    public $headquarter = "";
    public $date;

    public function mount(){
        $this->date = date('Y-m-d');
    }

    public function delete(){
        dd($this->type, $this->user, $this->headquarter, $this->date);
    }

    public function render()
    {
        return view('livewire.dsp.index');
    }
}
