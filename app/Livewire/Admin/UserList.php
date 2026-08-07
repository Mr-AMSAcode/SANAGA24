<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Title('User Management — Admin')]
class UserList extends Component
{
    use WithPagination;

    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * ID of the user whose role is currently being edited inline.
     * Null = no inline editor open.
     */
    public ?int $editingUserId = null;

    /**
     * The role being staged for the user being edited.
     */
    public string $pendingRole = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('admin.panel.view'), 403);
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────
    // Inline role editing
    // ─────────────────────────────────────────────────

    /**
     * Open the inline role editor for a specific user.
     */
    public function startEdit(int $userId): void
    {
        $user = User::with('roles')->findOrFail($userId);

        // Prevent editing other admins unless you are the super-admin (id=1)
        if ($user->hasRole('admin') && auth()->id() !== 1) {
            $this->addError('editingUserId', 'Only the super-admin can change another admin\'s role.');

            return;
        }

        $this->editingUserId = $userId;
        $this->pendingRole = $user->roles->first()?->name ?? 'user';
    }

    public function cancelEdit(): void
    {
        $this->editingUserId = null;
        $this->pendingRole = '';
    }

    /**
     * Persist the pending role change.
     */
    public function confirmRoleChange(): void
    {
        abort_unless(auth()->user()?->can('user.promote'), 403);

        $this->validate(['pendingRole' => 'required|in:user,editor,admin']);

        $user = User::findOrFail($this->editingUserId);

        // syncRoles replaces all existing roles with the new one.
        $user->syncRoles([$this->pendingRole]);

        $this->editingUserId = null;
        $this->pendingRole = '';

        session()->flash('success', "{$user->name}'s role updated to {$this->pendingRole}.");
    }

    /**
     * Convenience shortcut: promote a user to editor with one click.
     */
    public function promoteToEditor(int $userId): void
    {
        abort_unless(auth()->user()?->can('user.promote'), 403);

        $user = User::findOrFail($userId);
        $user->syncRoles(['editor']);
        session()->flash('success', "{$user->name} promoted to editor.");
    }

    /**
     * Convenience shortcut: demote an editor back to regular user.
     */
    public function demoteToUser(int $userId): void
    {
        abort_unless(auth()->user()?->can('user.demote'), 403);

        $user = User::findOrFail($userId);
        $user->syncRoles(['user']);
        session()->flash('success', "{$user->name} demoted to user.");
    }

    // ─────────────────────────────────────────────────
    // Computed
    // ─────────────────────────────────────────────────

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('roles:name')
            ->when($this->roleFilter, fn ($q) => $q->role($this->roleFilter))
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('email', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function roleCounts(): array
    {
        return User::selectRaw('roles.name, count(*) as total')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->groupBy('roles.name')
            ->pluck('total', 'roles.name')
            ->toArray();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.admin.user-list');
    }
}
