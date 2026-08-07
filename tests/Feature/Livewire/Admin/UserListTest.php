<?php

/**
 * tests/Feature/Livewire/Admin/UserListTest.php
 *
 * Covers App\Livewire\Admin\UserList: access control, search/role
 * filtering, the role-count summary, and role management (inline edit,
 * quick promote/demote, and the "only the super-admin touches another
 * admin" guard).
 */

use App\Livewire\Admin\UserList;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Force a user's primary key to 1 — the app's "super-admin" convention.
 * Safe here: RefreshDatabase rolls back every other test's rows, so no
 * other row can already occupy id=1 within this test's transaction.
 */
function makeSuperAdmin(): User
{
    $admin = User::factory()->admin()->create();
    $oldId = $admin->id;

    DB::table('users')->where('id', $oldId)->update(['id' => 1]);
    // Spatie's role pivot points at the old id — repoint it or the
    // fresh id=1 user would look roleless.
    DB::table('model_has_roles')->where('model_id', $oldId)->update(['model_id' => 1]);

    return User::findOrFail(1);
}

describe('access control', function () {
    it('denies regular users', function () {
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($user)->test(UserList::class)->assertForbidden();
    });

    it('denies editors', function () {
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($editor)->test(UserList::class)->assertForbidden();
    });

    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(UserList::class)->assertOk();
    });
});

describe('users()', function () {
    it('filters by role', function () {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        User::factory()->asUser()->create();

        $users = Livewire::actingAs($admin)
            ->test(UserList::class)
            ->set('roleFilter', 'editor')
            ->instance()->users();

        expect($users->pluck('id')->toArray())->toBe([$editor->id]);
    });

    it('searches by name or email', function () {
        $admin = User::factory()->admin()->create();
        $match = User::factory()->asUser()->create(['name' => 'Zendaya Okafor', 'email' => 'zen@example.com']);
        User::factory()->asUser()->create(['name' => 'Someone Else', 'email' => 'someone@example.com']);

        $byName = Livewire::actingAs($admin)->test(UserList::class)->set('search', 'zendaya')->instance()->users();
        expect($byName->pluck('id')->toArray())->toBe([$match->id]);

        $byEmail = Livewire::actingAs($admin)->test(UserList::class)->set('search', 'zen@example')->instance()->users();
        expect($byEmail->pluck('id')->toArray())->toBe([$match->id]);
    });
});

describe('roleCounts()', function () {
    it('counts users per role', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->editor()->count(2)->create();
        User::factory()->asUser()->count(3)->create();

        $counts = Livewire::actingAs($admin)->test(UserList::class)->instance()->roleCounts();

        expect($counts['admin'])->toBe(1)
            ->and($counts['editor'])->toBe(2)
            ->and($counts['user'])->toBe(3);
    });
});

describe('inline role editing', function () {
    it('starts editing and preloads the user\'s current role', function () {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($admin)->test(UserList::class)->call('startEdit', $editor->id);

        expect($component->get('editingUserId'))->toBe($editor->id)
            ->and($component->get('pendingRole'))->toBe('editor');
    });

    it('cancels editing and resets the pending state', function () {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        $component = Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('startEdit', $editor->id)
            ->call('cancelEdit');

        expect($component->get('editingUserId'))->toBeNull()
            ->and($component->get('pendingRole'))->toBe('');
    });

    it('blocks a regular admin from editing another admin\'s role', function () {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(UserList::class)->call('startEdit', $otherAdmin->id);

        expect($component->get('editingUserId'))->toBeNull();
        $component->assertHasErrors('editingUserId');
    });

    it('lets the super-admin (id 1) edit another admin\'s role', function () {
        $superAdmin = makeSuperAdmin();
        $otherAdmin = User::factory()->admin()->create();

        $component = Livewire::actingAs($superAdmin)->test(UserList::class)->call('startEdit', $otherAdmin->id);

        expect($component->get('editingUserId'))->toBe($otherAdmin->id);
    });

    it('confirms a role change and syncs the new role', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('startEdit', $user->id)
            ->set('pendingRole', 'editor')
            ->call('confirmRoleChange');

        expect($user->fresh()->hasRole('editor'))->toBeTrue()
            ->and($user->fresh()->hasRole('user'))->toBeFalse();
    });

    it('rejects an invalid role on confirm', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('startEdit', $user->id)
            ->set('pendingRole', 'superuser')
            ->call('confirmRoleChange')
            ->assertHasErrors(['pendingRole']);

        expect($user->fresh()->hasRole('user'))->toBeTrue();
    });
});

describe('quick promote/demote', function () {
    it('promotes a user straight to editor', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->asUser()->create();

        Livewire::actingAs($admin)->test(UserList::class)->call('promoteToEditor', $user->id);

        expect($user->fresh()->hasRole('editor'))->toBeTrue();
    });

    it('demotes an editor straight to user', function () {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        Livewire::actingAs($admin)->test(UserList::class)->call('demoteToUser', $editor->id);

        expect($editor->fresh()->hasRole('user'))->toBeTrue();
    });
});
