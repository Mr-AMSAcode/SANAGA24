<div class="min-h-screen bg-stone-50">
    <div class="max-w-6xl mx-auto px-6 py-10">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-black text-stone-900 tracking-tight">User Management</h1>
            <p class="text-stone-500 text-sm mt-0.5">Manage roles and access for all registered users</p>
        </div>

        {{-- Flash --}}
        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('editingUserId'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ $errors->first('editingUserId') }}
            </div>
        @endif

        {{-- Role count summary --}}
        <div class="flex flex-wrap gap-3 mb-6">
            @foreach ($this->roles as $role)
                <button
                    wire:click="$set('roleFilter', '{{ $roleFilter === $role->name ? '' : $role->name }}')"
                    @class([
                        'px-4 py-2 rounded-lg text-sm font-semibold transition-colors border',
                        'bg-stone-900 text-white border-stone-900' => $roleFilter === $role->name,
                        'bg-white text-stone-600 border-stone-200 hover:border-stone-400' => $roleFilter !== $role->name,
                    ])
                >
                    {{ ucfirst($role->name) }}
                    <span class="ml-1.5 text-xs opacity-60">{{ $this->roleCounts[$role->name] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Search --}}
        <div class="relative mb-6 max-w-sm">
            <span class="absolute inset-y-0 left-3 flex items-center text-stone-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
            </span>
            <input wire:model.live.debounce.400ms="search" type="search"
                   placeholder="Search by name or email…"
                   class="w-full pl-9 pr-4 py-2 text-sm border border-stone-200 rounded-lg bg-white
                          focus:outline-none focus:ring-2 focus:ring-amber-400"/>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                <tr class="border-b border-stone-100 text-left text-xs text-stone-500 uppercase tracking-wider">
                    <th class="px-5 py-3 font-semibold">User</th>
                    <th class="px-4 py-3 font-semibold">Role</th>
                    <th class="px-4 py-3 font-semibold">Joined</th>
                    <th class="px-4 py-3 font-semibold text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-stone-50">
                @forelse ($this->users as $user)
                    <tr class="hover:bg-stone-50 transition-colors group">

                        {{-- User info --}}
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-stone-200 flex items-center justify-center
                                                text-xs font-bold text-stone-600 flex-shrink-0 uppercase">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-stone-800">{{ $user->name }}</p>
                                    <p class="text-xs text-stone-400">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Role / Inline role editor --}}
                        <td class="px-4 py-4">
                            @if ($editingUserId === $user->id)
                                <div class="flex items-center gap-2">
                                    <select wire:model="pendingRole"
                                            class="text-sm border border-amber-400 rounded-lg px-2 py-1 bg-white
                                                       focus:outline-none focus:ring-2 focus:ring-amber-400">
                                        <option value="user">User</option>
                                        <option value="editor">Editor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button wire:click="confirmRoleChange"
                                            class="p-1.5 text-green-600 hover:bg-green-50 rounded-md transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    <button wire:click="cancelEdit"
                                            class="p-1.5 text-stone-400 hover:bg-stone-100 rounded-md transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            @else
                                @php $role = $user->roles->first()?->name ?? 'user'; @endphp
                                <span @class([
                                        'inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full',
                                        'bg-purple-100 text-purple-700' => $role === 'admin',
                                        'bg-blue-100 text-blue-700'   => $role === 'editor',
                                        'bg-stone-100 text-stone-600' => $role === 'user',
                                    ])>
                                        {{ ucfirst($role) }}
                                    </span>
                            @endif
                        </td>

                        {{-- Joined --}}
                        <td class="px-4 py-4 text-xs text-stone-400">
                            {{ $user->created_at->format('d M Y') }}
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-4 text-right">
                            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">

                                {{-- Edit role --}}
                                @if ($editingUserId !== $user->id)
                                    <button wire:click="startEdit({{ $user->id }})"
                                            title="Edit role"
                                            class="p-1.5 text-stone-400 hover:text-stone-700 rounded-md hover:bg-stone-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536
                                                         L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </button>
                                @endif

                                {{-- Quick promote to editor --}}
                                @if (($user->roles->first()?->name ?? 'user') === 'user')
                                    <button wire:click="promoteToEditor({{ $user->id }})"
                                            title="Promote to editor"
                                            class="p-1.5 text-blue-400 hover:text-blue-700 rounded-md hover:bg-blue-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                    </button>
                                @endif

                                {{-- Demote editor back to user --}}
                                @if (($user->roles->first()?->name ?? '') === 'editor')
                                    <button wire:click="demoteToUser({{ $user->id }})"
                                            title="Demote to user"
                                            wire:confirm="Demote {{ $user->name }} back to regular user?"
                                            class="p-1.5 text-orange-400 hover:text-orange-700 rounded-md hover:bg-orange-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                        </svg>
                                    </button>
                                @endif

                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-16 text-center text-stone-400">
                            No users match your filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->users->hasPages())
            <div class="mt-6">{{ $this->users->links() }}</div>
        @endif

    </div>
</div>
