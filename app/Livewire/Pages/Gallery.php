<?php

namespace App\Livewire\Pages;

use App\Models\Picture;
use App\Models\Video;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Site-wide media gallery — every image and video from every published
 * post, newest first. Not a Post category: there's no "gallery" article
 * section, this just aggregates the Picture/Video rows that already exist
 * across the site. Photos and videos are shown in separate tabs (rather
 * than one interleaved feed) since they're two different Eloquent models —
 * each keeps its own pagination.
 */
#[Title('Gallery — Sanaga24')]
class Gallery extends Component
{
    use WithPagination;

    public string $tab = 'photos'; // 'photos' | 'videos'

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    #[Computed]
    public function pictures()
    {
        return Picture::query()
            ->whereHas('post', fn ($q) => $q->published())
            ->with('post:id,title,slug')
            ->latest('created_at')
            ->paginate(24);
    }

    #[Computed]
    public function videos()
    {
        return Video::query()
            ->whereHas('post', fn ($q) => $q->published())
            ->with('post:id,title,slug')
            ->latest('created_at')
            ->paginate(24);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.gallery');
    }
}
