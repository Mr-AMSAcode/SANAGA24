<?php

namespace App\Livewire\Pages;

use App\Enums\PostSection;
use App\Models\Post;
use App\Models\Tag;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Browse Articles — Sanaga24')]
class PostIndex extends Component
{
    use WithPagination;

    /** Section being browsed — populated from the route parameter in mount() */
    #[Url(as: 'section', except: '')]
    public string $section = '';

    /** Tag slug being browsed — populated from the route parameter in mount() */
    #[Url(as: 'tag', except: '')]
    public string $tag = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'latest')]
    public string $sort = 'latest'; // 'latest' | 'popular' | 'commented'

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Called when the component is mounted via route:
     *   /section/{section}  or  /tags/{tag}
     * The section parameter is validated against the enum; the tag
     * parameter is validated against the tags table.
     */
    public function mount(string $section = '', string $tag = ''): void
    {
        if ($section !== '') {
            // Validate the section is a valid enum value
            $valid = array_map(fn ($c) => $c->value, PostSection::cases());
            abort_unless(in_array($section, $valid), 404);
            $this->section = $section;
        }

        if ($tag !== '') {
            abort_unless(Tag::where('slug', $tag)->exists(), 404);
            $this->tag = $tag;
        }
    }

    #[Computed]
    public function posts()
    {
        $hasSearch = filled($this->search);

        $query = Post::query()
            ->published()
            ->with([
                'editor:id,name',
                'pictures' => fn ($q) => $q->featured(),
                'stats:post_id,view_count,like_count,comment_count',
            ])
            ->when($this->section, fn ($q) => $q->inSection($this->section))
            ->when($this->tag, fn ($q) => $q->withTagSlug($this->tag))
            ->when($hasSearch, fn ($q) => $q->search($this->search));

        return match ($this->sort) {
            'popular' => $query->join('post_stats', 'posts.id', '=', 'post_stats.post_id')
                ->orderByDesc('post_stats.view_count')
                ->select('posts.*')
                ->paginate(15),
            'commented' => $query->join('post_stats', 'posts.id', '=', 'post_stats.post_id')
                ->orderByDesc('post_stats.comment_count')
                ->select('posts.*')
                ->paginate(15),
            default => $hasSearch
                ? $query->orderByRelevance($this->search)->paginate(15)
                : $query->latest()->paginate(15),
        };
    }

    #[Computed]
    public function sections(): array
    {
        return PostSection::visible();
    }

    #[Computed]
    public function currentSectionLabel(): string
    {
        if ($this->tag !== '') {
            return '#'.($this->currentTag?->name ?? $this->tag);
        }

        if ($this->section === '') {
            return __('All Sections');
        }

        $match = collect(PostSection::cases())
            ->firstWhere('value', $this->section);

        return $match ? __($match->label()) : ucfirst($this->section);
    }

    #[Computed]
    public function currentTag(): ?Tag
    {
        return $this->tag !== '' ? Tag::where('slug', $this->tag)->first() : null;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.post-index');
    }
}
