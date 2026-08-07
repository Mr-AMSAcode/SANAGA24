<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostSummaryResource;
use App\Models\Post;
use Illuminate\Http\Request;

/**
 * Public, read-only JSON endpoints over published posts. Unpublished posts
 * (draft/scheduled/archived) never appear here regardless of slug/id/filter
 * guessing — every query is anchored on Post::published().
 */
class PostController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->filled('q') ? (string) $request->input('q') : null;

        $posts = Post::query()
            ->published()
            ->when($request->filled('section'), fn ($q) => $q->inSection((string) $request->input('section')))
            ->when($request->filled('tag'), fn ($q) => $q->withTagSlug((string) $request->input('tag')))
            ->when($request->filled('author'), fn ($q) => $q->byEditor((int) $request->input('author')))
            ->when($search, fn ($q) => $q->search($search)->orderByRelevance($search))
            ->when(! $search, fn ($q) => $q->latest('id'))
            ->with([
                'editor',
                'tags',
                'pictures' => fn ($q) => $q->featured(),
                'stats',
                'postStatus',
            ])
            ->paginate(15);

        return PostSummaryResource::collection($posts);
    }

    public function show(string $slug)
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with(['editor', 'tags', 'pictures', 'stats', 'postStatus'])
            ->firstOrFail();

        return new PostResource($post);
    }
}
