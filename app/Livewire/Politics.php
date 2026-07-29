<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Sanaga24 — Politiques')]
class Politics extends Component
{
    public function render()
    {
        return view('livewire.politics');
    }
}
