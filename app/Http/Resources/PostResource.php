<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single-post detail shape — full content and the whole picture gallery,
 * rather than the excerpt/featured-image-only shape used for listings.
 *
 * Assumes the controller eager-loaded: editor, tags, pictures, stats,
 * postStatus.
 */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'section' => $this->section->value,
            'content' => $this->content,
            'published_at' => $this->postStatus?->active_period_start?->toIso8601String(),
            'author' => new AuthorResource($this->editor),
            'tags' => TagResource::collection($this->tags),
            'pictures' => PictureResource::collection($this->pictures),
            'stats' => [
                'views' => $this->stats?->view_count ?? 0,
                'likes' => $this->stats?->like_count ?? 0,
                'comments' => $this->stats?->comment_count ?? 0,
            ],
        ];
    }
}
