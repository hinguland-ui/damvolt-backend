<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdmin();
        $this->call(ContentSeeder::class);
    }

    /**
     * The admin account comes from .env (ADMIN_EMAIL / ADMIN_PASSWORD) — no password lives in the code.
     * An existing account is never touched, so re-running the seeder cannot reset a changed password.
     */
    private function createAdmin(): void
    {
        $email = config('app.seed_admin_email');

        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = config('app.seed_admin_password') ?: Str::password(16, symbols: false);

        User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->command?->info("Admin created: {$email}");
        if (! config('app.seed_admin_password')) {
            $this->command?->warn("ADMIN_PASSWORD was not set, so a random password was generated — note it now (it is shown only once): {$password}");
        }
    }
}
