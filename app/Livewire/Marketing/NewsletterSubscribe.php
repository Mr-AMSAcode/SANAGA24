<?php

namespace App\Livewire\Marketing;

use App\Models\NewsletterSubscriber;
use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * Footer newsletter signup — embeddable on any page, works for guests
 * and authenticated users alike. No account required to subscribe.
 */
class NewsletterSubscribe extends Component
{
    #[Rule('required|email|max:255')]
    public string $email = '';

    public bool $subscribed = false;

    public function subscribe(): void
    {
        $this->validate();

        NewsletterSubscriber::subscribe($this->email, auth()->user());

        $this->subscribed = true;
        $this->email = '';
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.marketing.newsletter-subscribe');
    }
}
