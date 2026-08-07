<?php

namespace App\Http\Controllers;

use App\Enums\PostSection;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * XML sitemap: static pages + every published post.
     * Cached briefly — identical for every visitor and crawler hit, and
     * rebuilding it re-queries every published post.
     */
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(15), function () {
            $posts = Post::query()
                ->published()
                ->orderByDesc('updated_at')
                ->get(['slug', 'updated_at']);

            return view('sitemap', [
                'posts' => $posts,
                'sections' => PostSection::cases(),
            ])->render();
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
