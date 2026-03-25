<?php

namespace App\Livewire\Headquarters;

use App\Models\Headquarter;
use Livewire\Component;

class Index extends Component
{
    public $search = '';
    public $headquarters;

    public function mount()
    {
        $term = trim($this->search);
        $this->headquarters = Headquarter::query()
            ->when($term !== '', fn ($q) =>
                $q->where('name', 'like', "%{$term}%")
            )
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch()
    {
        $this->mount();
    }

    public function export(): void
    {
        $this->dispatch('url-open', ['url' => route('exports.headquarters', ['search' => $this->search])]);
    }

    public function render()
    {
        return view('livewire.headquarters.index');
    }
}
