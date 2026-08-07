<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\Post;
use App\Models\User;

class AuthorController extends Controller
{
    /**
     * Same rule as the public author page: a reader account with no
     * published bylines has no public profile to show.
     */
    public function show(User $author)
    {
        abort_unless(Post::published()->byEditor($author->id)->exists(), 404);

        return (new AuthorResource($author))->additional([
            'published_post_count' => Post::published()->byEditor($author->id)->count(),
        ]);
    }
}
