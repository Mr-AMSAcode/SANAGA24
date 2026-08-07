<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class RssFeedController extends Controller
{
    /**
     * RSS 2.0 feed of the 50 most recent published posts.
     */
    public function __invoke(): Response
    {
        $xml = Cache::remember('rss.xml', now()->addMinutes(15), function () {
            $posts = Post::query()
                ->published()
                ->with('editor:id,name')
                ->latest()
                ->limit(50)
                ->get();

            return view('rss', ['posts' => $posts])->render();
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
