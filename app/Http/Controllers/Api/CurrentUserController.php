<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Minimal authenticated endpoint proving the Sanctum token guard works
 * end-to-end — the conventional Laravel /api/user smoke test.
 */
class CurrentUserController extends Controller
{
    public function __invoke(Request $request)
    {
        return $request->user();
    }
}
