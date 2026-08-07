<?php

namespace App\Livewire\Admin;

use App\Models\NewsletterSubscriber;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Newsletter Subscribers — Admin')]
class NewsletterSubscriberList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.panel.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function subscribers()
    {
        return NewsletterSubscriber::query()
            ->active()
            ->when($this->search, fn ($q) => $q->where('email', 'ilike', "%{$this->search}%"))
            ->latest('subscribed_at')
            ->paginate(25);
    }

    #[Computed]
    public function activeCount(): int
    {
        return NewsletterSubscriber::active()->count();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.newsletter-subscriber-list');
    }
}
