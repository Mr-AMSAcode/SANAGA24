<?php

use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\PostList as AdminPostList;
use App\Livewire\Admin\UserList as AdminUserList;
use App\Livewire\Culture;
use App\Livewire\Editor\Dashboard as EditorDashboard;
use App\Livewire\Editor\PostCreate as EditorPostCreate;
use App\Livewire\Editor\PostEdit as EditorPostEdit;
use App\Livewire\Editor\PostList as EditorPostList;
use App\Livewire\Opinion;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PostIndex;
use App\Livewire\Pages\PostShow;
use App\Livewire\Politics;
use App\Livewire\Science;
use App\Livewire\Sports;
use App\Livewire\World;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

// links for all the categories of the newspaper.
Route::get('/politics', Politics::class)->name('politics');
Route::get('/sports', Sports::class)->name('sports');
Route::get('/culture', Culture::class)->name('culture');
Route::get('/science', Science::class)->name('science');
Route::get('/opinion', Opinion::class)->name('opinion');
Route::get('/world', World::class)->name('world');

Route::get('/posts/{post:slug}', PostShow::class)->name('posts.show');
Route::get('/section/{section}', PostIndex::class)->name('posts.section');
Route::get('/browse', PostIndex::class)->name('posts.index');

// ── Editor panel  (auth + role:editor,admin) ─────────────────────────────────
Route::middleware(['auth', 'verified', 'can:editor.panel.view'])->prefix('editor')->name('editor.')->group(function () {
    // Route::get('/', EditorDashboard::class)->name('dashboard');
    // Route::get('/posts', EditorPostList::class)->name('posts');
    // Route::get('/posts/create', EditorPostCreate::class)->name('posts.create');
    // Route::get('/posts/{post:slug}/edit', EditorPostEdit::class)->name('posts.edit');
});

// ── Admin panel  (auth + role:admin) ─────────────────────────────────────────
Route::middleware(['auth', 'verified', 'can:admin.panel.view'])->prefix('admin')->name('admin.')->group(function () {
    // Route::get('/', AdminDashboard::class)->name('dashboard');
    // Route::get('/posts', AdminPostList::class)->name('posts');
    // Route::get('/users', AdminUserList::class)->name('users');
});
require __DIR__ . '/settings.php';
