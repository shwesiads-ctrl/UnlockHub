<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@unlockhub.test');

        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = (string) env('ADMIN_PASSWORD', '');
        if (strlen($password) < 12) {
            throw new \RuntimeException('Set ADMIN_PASSWORD to a unique password of at least 12 characters before creating the first admin account.');
        }

        $admin = new User([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => $email,
            'password' => $password,
        ]);
        $admin->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();

        $this->command?->warn("Admin created: {$email} — change the password after first login.");
    }
}
