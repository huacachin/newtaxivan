<?php

namespace App\Livewire\Auth;

use Livewire\Component;

class Logout extends Component
{
    public function questionLogout()
        {
           auth()->logout();
           return redirect()->route('login');
        }

    public function render()
    {
        return view('livewire.auth.logout');
    }
}
