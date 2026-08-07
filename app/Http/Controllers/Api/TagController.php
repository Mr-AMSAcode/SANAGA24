<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;

class TagController extends Controller
{
    /**
     * Only tags actually carried by at least one published post — a tag
     * that only exists on drafts has nothing public to show for it.
     */
    public function index()
    {
        $tags = Tag::query()
            ->whereHas('posts', fn ($q) => $q->published())
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }
}
