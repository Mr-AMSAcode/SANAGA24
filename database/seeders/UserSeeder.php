<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── Extra editors (so posts are spread across multiple authors) ───
        User::factory()
            ->editor()
            ->count(4)
            ->create();

        // ── Regular readers (needed for comments and likes) ───────────────
        User::factory()
            ->asUser()
            ->count(30)
            ->create();

        $this->command->info('  ✓ ' . User::count() . ' total users seeded.');
    }
}
