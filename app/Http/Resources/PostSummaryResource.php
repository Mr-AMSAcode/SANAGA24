<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Shape used for post listings — an excerpt rather than full content.
 * Pairs with PostResource for the single-post detail view.
 *
 * Assumes the controller eager-loaded: editor, tags, pictures, stats,
 * postStatus.
 */
class PostSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'section' => $this->section->value,
            'excerpt' => Str::limit(strip_tags($this->content), 200),
            'featured_image_url' => $this->pictures->firstWhere('is_featured', true)?->url,
            'published_at' => $this->postStatus?->active_period_start?->toIso8601String(),
            'author' => new AuthorResource($this->editor),
            'tags' => TagResource::collection($this->tags),
            'stats' => [
                'views' => $this->stats?->view_count ?? 0,
                'likes' => $this->stats?->like_count ?? 0,
                'comments' => $this->stats?->comment_count ?? 0,
            ],
        ];
    }
}
