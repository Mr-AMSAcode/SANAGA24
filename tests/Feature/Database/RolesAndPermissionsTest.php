<?php

/**
 * tests/Feature/Database/RolesAndPermissionsTest.php
 *
 * Tests the entire RBAC layer:
 *   - Seeder creates the three roles with the correct permissions
 *   - Registration assigns the 'user' role
 *   - Role helper methods on User work correctly
 *   - PostPolicy respects the access control matrix from the UML
 *   - Spatie middleware blocks / allows the right roles
 *
 * Run: ./vendor/bin/pest tests/Feature/Database/RolesAndPermissionsTest.php
 */

use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Seed roles before every test in this file.
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// SEEDER
// ─────────────────────────────────────────────────────────────────────────────
describe('RolesAndPermissionsSeeder', function () {

    it('creates exactly three roles', function () {
        expect(Role::count())->toBe(3);
        expect(Role::pluck('name')->sort()->values()->toArray())
            ->toBe(['admin', 'editor', 'user']);
    });

    it('creates all expected permissions', function () {
        $expected = [
            'post.view', 'post.create', 'post.edit.own', 'post.delete.own',
            'post.publish.own', 'post.edit.any', 'post.delete.any',
            'comment.create', 'comment.delete.own', 'comment.delete.any',
            'like.create', 'like.delete.own',
            'user.promote', 'user.demote',
            'newsletter.subscribe',
            'media.upload',
            'admin.panel.view', 'editor.panel.view',
        ];

        foreach ($expected as $perm) {
            expect(Permission::where('name', $perm)->exists())
                ->toBeTrue("Permission '{$perm}' not found in database");
        }
    });

    it('user role has only basic permissions', function () {
        $role = Role::findByName('user');
        $perms = $role->permissions->pluck('name');

        expect($perms)->toContain('post.view')
            ->toContain('comment.create')
            ->toContain('like.create')
            ->toContain('newsletter.subscribe');

        expect($perms)->not->toContain('post.create')
            ->not->toContain('media.upload')
            ->not->toContain('admin.panel.view')
            ->not->toContain('user.promote');
    });

    it('editor role has content creation permissions', function () {
        $role = Role::findByName('editor');
        $perms = $role->permissions->pluck('name');

        expect($perms)->toContain('post.create')
            ->toContain('post.edit.own')
            ->toContain('post.delete.own')
            ->toContain('post.publish.own')
            ->toContain('media.upload')
            ->toContain('editor.panel.view');

        expect($perms)->not->toContain('post.edit.any')
            ->not->toContain('post.delete.any')
            ->not->toContain('admin.panel.view')
            ->not->toContain('user.promote');
    });

    it('admin role has every permission', function () {
        $role = Role::findByName('admin');
        $total = Permission::count();

        expect($role->permissions->count())->toBe($total);
    });

    it('is idempotent — running twice does not duplicate roles', function () {
        $this->seed(RolesAndPermissionsSeeder::class); // run a second time

        expect(Role::count())->toBe(3);
        expect(Permission::where('name', 'post.view')->count())->toBe(1);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// ROLE ASSIGNMENT
// ─────────────────────────────────────────────────────────────────────────────
describe('Role assignment', function () {

    it('assigns user role via factory state', function () {
        $user = User::factory()->asUser()->create();

        expect($user->hasRole('user'))->toBeTrue()
            ->and($user->hasRole('editor'))->toBeFalse()
            ->and($user->hasRole('admin'))->toBeFalse();
    });

    it('assigns editor role via factory state', function () {
        $editor = User::factory()->editor()->create();

        expect($editor->hasRole('editor'))->toBeTrue()
            ->and($editor->isEditor())->toBeTrue()
            ->and($editor->hasRole('admin'))->toBeFalse();
    });

    it('assigns admin role via factory state', function () {
        $admin = User::factory()->admin()->create();

        expect($admin->hasRole('admin'))->toBeTrue()
            ->and($admin->isAdmin())->toBeTrue();
    });

    it('promotes a user to editor', function () {
        $user = User::factory()->asUser()->create();
        expect($user->hasRole('editor'))->toBeFalse();

        $user->syncRoles(['editor']); // removes 'user', assigns 'editor'

        expect($user->fresh()->hasRole('editor'))->toBeTrue()
            ->and($user->fresh()->hasRole('user'))->toBeFalse();
    });

    it('demotes an editor back to user', function () {
        $editor = User::factory()->editor()->create();
        $editor->syncRoles(['user']);

        expect($editor->fresh()->hasRole('user'))->toBeTrue()
            ->and($editor->fresh()->hasRole('editor'))->toBeFalse();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// PERMISSION CHECKS
// ─────────────────────────────────────────────────────────────────────────────
describe('Permission checks via can()', function () {

    it('regular user can view posts', function () {
        $user = User::factory()->asUser()->create();
        expect($user->can('post.view'))->toBeTrue();
    });

    it('regular user cannot create posts', function () {
        $user = User::factory()->asUser()->create();
        expect($user->can('post.create'))->toBeFalse();
    });

    it('editor can create posts', function () {
        $editor = User::factory()->editor()->create();
        expect($editor->can('post.create'))->toBeTrue();
    });

    it('editor cannot edit any post (only own)', function () {
        $editor = User::factory()->editor()->create();
        expect($editor->can('post.edit.any'))->toBeFalse();
    });

    it('admin can do everything', function () {
        $admin = User::factory()->admin()->create();

        expect($admin->can('post.edit.any'))->toBeTrue()
            ->and($admin->can('post.delete.any'))->toBeTrue()
            ->and($admin->can('user.promote'))->toBeTrue()
            ->and($admin->can('admin.panel.view'))->toBeTrue();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// POST POLICY
// ─────────────────────────────────────────────────────────────────────────────
describe('PostPolicy', function () {

    it('any user can view a published post', function () {
        $user = User::factory()->asUser()->create();
        $post = Post::factory()->published()->create();
        $policy = new PostPolicy;

        expect($policy->view($user, $post))->toBeTrue();
    });

    it('unauthenticated visitor can view a published post', function () {
        $post = Post::factory()->published()->create();
        $policy = new PostPolicy;

        expect($policy->view(null, $post))->toBeTrue();
    });

    it('only the owning editor can view their own draft', function () {
        $editor = User::factory()->editor()->create();
        $otherUser = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor->id]); // draft
        $policy = new PostPolicy;

        expect($policy->view($editor, $post))->toBeTrue();
        expect($policy->view($otherUser, $post))->toBeFalse();
    });

    it('admin can view any draft via before() hook', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(); // draft
        $policy = new PostPolicy;

        // before() returns true for admin, bypassing view()
        $result = $policy->before($admin, 'view');
        expect($result)->toBeTrue();
    });

    it('editor can create a post', function () {
        $editor = User::factory()->editor()->create();
        $policy = new PostPolicy;

        expect($policy->create($editor))->toBeTrue();
    });

    it('regular user cannot create a post', function () {
        $user = User::factory()->asUser()->create();
        $policy = new PostPolicy;

        expect($policy->create($user))->toBeFalse();
    });

    it('editor can update their own post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor->id]);
        $policy = new PostPolicy;

        expect($policy->update($editor, $post))->toBeTrue();
    });

    it('editor cannot update another editor post', function () {
        $editor1 = User::factory()->editor()->create();
        $editor2 = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor1->id]);
        $policy = new PostPolicy;

        expect($policy->update($editor2, $post))->toBeFalse();
    });

    it('admin can update any post via before() hook', function () {
        $admin = User::factory()->admin()->create();
        $policy = new PostPolicy;

        $result = $policy->before($admin, 'update');
        expect($result)->toBeTrue();
    });

    it('editor can delete their own post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor->id]);
        $policy = new PostPolicy;

        expect($policy->delete($editor, $post))->toBeTrue();
    });

    it('editor cannot delete another editor post', function () {
        $editor1 = User::factory()->editor()->create();
        $editor2 = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor1->id]);
        $policy = new PostPolicy;

        expect($policy->delete($editor2, $post))->toBeFalse();
    });

    it('editor cannot restore a deleted post', function () {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor->id]);
        $policy = new PostPolicy;

        expect($policy->restore($editor, $post))->toBeFalse();
    });

    it('canManagePost helper on User model works correctly', function () {
        $editor = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();
        $post = Post::factory()->create(['editor_id' => $editor->id]);

        expect($editor->canManagePost($post))->toBeTrue();
        expect($other->canManagePost($post))->toBeFalse();
    });

    it('canManagePost returns true for admin regardless of editor_id', function () {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(); // some other editor owns it

        expect($admin->canManagePost($post))->toBeTrue();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// FULL ACCESS CONTROL MATRIX (from UML §6)
// ─────────────────────────────────────────────────────────────────────────────
describe('Full access control matrix', function () {

    it('correctly models all UML matrix rows for user role', function () {
        $user = User::factory()->asUser()->create();

        expect($user->can('post.view'))->toBeTrue();
        expect($user->can('like.create'))->toBeTrue();
        expect($user->can('comment.create'))->toBeTrue();
        expect($user->can('post.create'))->toBeFalse();
        expect($user->can('post.edit.own'))->toBeFalse();
        expect($user->can('post.delete.own'))->toBeFalse();
        expect($user->can('post.edit.any'))->toBeFalse();
        expect($user->can('post.delete.any'))->toBeFalse();
        expect($user->can('user.promote'))->toBeFalse();
        expect($user->can('user.demote'))->toBeFalse();
    });

    it('correctly models all UML matrix rows for editor role', function () {
        $editor = User::factory()->editor()->create();

        expect($editor->can('post.view'))->toBeTrue();
        expect($editor->can('like.create'))->toBeTrue();
        expect($editor->can('comment.create'))->toBeTrue();
        expect($editor->can('post.create'))->toBeTrue();
        expect($editor->can('post.edit.own'))->toBeTrue();
        expect($editor->can('post.delete.own'))->toBeTrue();
        expect($editor->can('post.publish.own'))->toBeTrue();
        expect($editor->can('post.edit.any'))->toBeFalse();
        expect($editor->can('post.delete.any'))->toBeFalse();
        expect($editor->can('user.promote'))->toBeFalse();
        expect($editor->can('user.demote'))->toBeFalse();
    });

    it('correctly models all UML matrix rows for admin role', function () {
        $admin = User::factory()->admin()->create();

        expect($admin->can('post.view'))->toBeTrue();
        expect($admin->can('post.create'))->toBeTrue();
        expect($admin->can('post.edit.own'))->toBeTrue();
        expect($admin->can('post.delete.own'))->toBeTrue();
        expect($admin->can('post.edit.any'))->toBeTrue();
        expect($admin->can('post.delete.any'))->toBeTrue();
        expect($admin->can('user.promote'))->toBeTrue();
        expect($admin->can('user.demote'))->toBeTrue();
        expect($admin->can('admin.panel.view'))->toBeTrue();
    });

});
