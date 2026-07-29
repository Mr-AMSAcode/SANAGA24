<?php

namespace App\Livewire\Pages;

use App\Enums\PostSection;
use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Sanaga24 — Actualités')]
class Home extends Component
{
    /**
     * Per-section "load more" offsets.
     * Key = section slug, value = current limit (default 4).
     */
    public array $sectionLimits = [];

    public function mount(): void
    {
        // Initialize limits for all sections
        foreach (PostSection::cases() as $section) {
            $this->sectionLimits[$section->value] = 4;
        }
    }

    /**
     * Expand a section's post count by 4 more.
     */
    public function loadMoreSection(string $sectionSlug): void
    {
        if (array_key_exists($sectionSlug, $this->sectionLimits)) {
            $this->sectionLimits[$sectionSlug] += 4;
        }
    }

    //
    // Hero carousel: latest 3 published posts with images
    //
    #[Computed]
    public function heroPosts()
    {
        return Post::query()
            ->published()
            ->whereHas('pictures')
            ->with(['pictures', 'editor:id,name', 'stats:post_id,view_count'])
            ->latest()
            ->limit(3)
            ->get();
    }

    //
    // Trending ticker: 8 most-viewed posts today/this week
    //
    #[Computed]
    public function trendingPosts()
    {
        return Post::query()
            ->published()
            ->join('post_stats', 'posts.id', '=', 'post_stats.post_id')
            ->orderByDesc('post_stats.view_count')
            ->select('posts.*')
            ->limit(8)
            ->get();
    }

    //
    // "Recent News" section: latest 4 posts any section
    //
    #[Computed]
    public function recentSection()
    {
        return Post::query()
            ->published()
            ->with(['pictures', 'editor:id,name', 'stats:post_id,view_count,comment_count'])
            ->latest()
            ->limit(4)
            ->get();
    }

    //
    // Per-section blocks with alternating layouts
    //

    /**
     * Layout assignment per section
     */
    private function layoutForSection(PostSection $section): string
    {
        return match ($section->value) {
            'politics' => 'four-col',
            'crime' => 'list',
            'sports' => 'hero-mini',
            'business' => 'three-col',
            'education' => 'hero-mini',
            'culture' => 'three-col',
            'health' => 'list',
            'technology' => 'three-col',
            default => 'three-col',
        };
    }

    #[Computed]
    public function sectionBlocks(): array
    {
        $blocks = [];

        foreach (PostSection::cases() as $section) {
            $limit = $this->sectionLimits[$section->value] ?? 4;

            $posts = Post::query()
                ->published()
                ->inSection($section->value)
                ->with(['pictures', 'editor:id,name', 'stats:post_id,view_count,comment_count'])
                ->latest()
                ->limit($limit)
                ->get();

            if ($posts->isNotEmpty()) {
                $blocks[] = [
                    'slug' => $section->value,
                    'label' => $section->label(),
                    'layout' => $this->layoutForSection($section),
                    'posts' => $posts,
                ];
            }
        }

        return $blocks;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.home');
    }
}
