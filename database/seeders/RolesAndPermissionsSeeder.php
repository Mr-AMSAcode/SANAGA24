<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Command to run only this seeder:
 *   php artisan db:seed --class=RolesAndPermissionsSeeder
 *
 * Re-runnable safely: forgets cache, uses firstOrCreate.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All permission strings for the platform.
     * Grouped by domain for readability.
     */
    private array $permissions = [
        // ── Posts ─────────────────────────────────────
        'post.view',            // read any published post
        'post.create',          // create a new post (draft)
        'post.edit.own',        // edit own post
        'post.delete.own',      // soft-delete own post
        'post.publish.own',     // change own post draft → published
        'post.edit.any',        // admin: edit any editor's post
        'post.delete.any',      // admin: soft-delete any post

        // ── Comments ──────────────────────────────────
        'comment.create',       // write a comment on any published post
        'comment.delete.own',   // delete own comment
        'comment.delete.any',   // admin: delete any comment

        // ── Likes ─────────────────────────────────────
        'like.create',          // like a post or comment
        'like.delete.own',      // un-like

        // ── Users ─────────────────────────────────────
        'user.promote',         // admin: assign editor role to a user
        'user.demote',          // admin: remove editor role from a user

        // ── Newsletter ────────────────────────────────
        'newsletter.subscribe', // subscribe to weekly newsletter

        // ── Media ─────────────────────────────────────
        'media.upload',         // upload images / PDFs

        // ── Admin views ───────────────────────────────
        'admin.panel.view',     // access the /admin routes
        'editor.panel.view',    // access the /editor routes
    ];

    public function run(): void
    {
        // Reset cached roles and permissions to avoid stale data.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions (idempotent).
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Role: user ────────────────────────────────────────────────────
        // Every registered account starts here.
        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->syncPermissions([
            'post.view',
            'comment.create',
            'comment.delete.own',
            'like.create',
            'like.delete.own',
            'newsletter.subscribe',
        ]);

        // ── Role: editor ──────────────────────────────────────────────────
        // Inherits all user permissions plus content-creation permissions.
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions([
            'post.view',
            'post.create',
            'post.edit.own',
            'post.delete.own',
            'post.publish.own',
            'comment.create',
            'comment.delete.own',
            'like.create',
            'like.delete.own',
            'newsletter.subscribe',
            'media.upload',
            'editor.panel.view',
        ]);

        // ── Role: admin ───────────────────────────────────────────────────
        // Has every permission without exception.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        $this->command->info('✓ Roles and permissions seeded.');
        $this->command->table(
            ['Role', 'Permissions'],
            [
                ['user',   implode(', ', $user->permissions->pluck('name')->toArray())],
                ['editor', implode(', ', $editor->permissions->pluck('name')->toArray())],
                ['admin',  'ALL ('.Permission::count().' permissions)'],
            ]
        );
    }
}
