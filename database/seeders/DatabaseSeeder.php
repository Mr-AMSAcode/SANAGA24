<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles and permissions must come FIRST — factories assign roles.
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Create a seeded admin account for local development.
        //    Do NOT do this in production — use php artisan tinker instead.
        if (app()->isLocal()) {
            $admin = User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@sanaga24.local',
                'password' => Hash::make('password'),
            ]);
            $admin->assignRole('admin');

            $editor = User::factory()->create([
                'name' => 'Editor User',
                'email' => 'editor@sanaga24.local',
                'password' => Hash::make('password'),
            ]);
            $editor->assignRole('editor');

            // ── 3. Additional users (editors + readers) ───────────────────────
            $this->call(UserSeeder::class);

            // ── 4. Posts and all related content ─────────────────────────────
            $this->call(PostSeeder::class);

            $this->command->info('✓ Dev accounts created: admin@sanaga24.local / editor@sanaga24.local (password: password)');
        }
    }
}
