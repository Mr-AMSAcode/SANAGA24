<?php

use App\Enums\PostSection;
use App\Http\Controllers\Admin\NewsletterExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsletterUnsubscribeController;
use App\Http\Controllers\RssFeedController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Admin\CommentList as AdminCommentList;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\NewsletterSubscriberList as AdminNewsletterSubscriberList;
use App\Livewire\Admin\PostList as AdminPostList;
use App\Livewire\Admin\UserList as AdminUserList;
use App\Livewire\Editor\Dashboard as EditorDashboard;
use App\Livewire\Editor\PostCreate as EditorPostCreate;
use App\Livewire\Editor\PostEdit as EditorPostEdit;
use App\Livewire\Editor\PostList as EditorPostList;
use App\Livewire\Pages\AuthorShow;
use App\Livewire\Pages\Gallery;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PostIndex;
use App\Livewire\Pages\PostShow;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');

// Site-wide media gallery — not a PostSection, so it isn't part of the
// per-section loop below; it aggregates pictures across every post.
Route::get('/galerie', Gallery::class)->name('galerie');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/feed.xml', RssFeedController::class)->name('feed');

// One pretty route per category (e.g. /politics, /sports, .../qui-sommes-nous)
// — each is PostIndex pre-filtered to its section via a route default, so it
// reuses the same query/pagination/sort logic as /browse. Driven by the enum
// so every current and future section gets a route automatically.
foreach (PostSection::cases() as $section) {
    Route::get('/'.$section->value, PostIndex::class)
        ->name($section->value)
        ->defaults('section', $section->value);
}

Route::get('/posts/{post:slug}', PostShow::class)->name('posts.show');
Route::get('/section/{section}', PostIndex::class)->name('posts.section');
Route::get('/tags/{tag}', PostIndex::class)->name('posts.tag');
Route::get('/browse', PostIndex::class)->name('posts.index');
Route::get('/authors/{author}', AuthorShow::class)->name('authors.show');
Route::get('/newsletter/unsubscribe/{token}', NewsletterUnsubscribeController::class)->name('newsletter.unsubscribe');

// ── Editor panel  (auth + role:editor,admin) ─────────────────────────────────
Route::middleware(['auth', 'verified', 'can:editor.panel.view'])->prefix('editor')->name('editor.')->group(function () {
    Route::get('/', EditorDashboard::class)->name('dashboard');
    Route::get('/posts', EditorPostList::class)->name('posts');
    Route::get('/posts/create', EditorPostCreate::class)->name('posts.create');
    Route::get('/posts/{post:slug}/edit', EditorPostEdit::class)->name('posts.edit');
});

// ── Admin panel  (auth + role:admin) ─────────────────────────────────────────
Route::middleware(['auth', 'verified', 'can:admin.panel.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboard::class)->name('dashboard');
    Route::get('/posts', AdminPostList::class)->name('posts');
    Route::get('/users', AdminUserList::class)->name('users');
    Route::get('/comments', AdminCommentList::class)->name('comments');
    Route::get('/newsletter', AdminNewsletterSubscriberList::class)->name('newsletter');
    Route::get('/newsletter/export', NewsletterExportController::class)->name('newsletter.export');
});
require __DIR__.'/settings.php';
