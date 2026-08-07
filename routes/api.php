<?php

use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

// Public, read-only — no authentication required.
Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
Route::get('/posts/{slug}', [PostController::class, 'show'])->name('api.posts.show');
Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
Route::get('/authors/{author}', [AuthorController::class, 'show'])->name('api.authors.show');

// Sanctum-protected — requires a bearer token from Settings > API Tokens.
Route::middleware('auth:sanctum')->get('/user', CurrentUserController::class)->name('api.user');
