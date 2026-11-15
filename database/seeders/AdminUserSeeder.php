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

        $admin = new User([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => $email,
            'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
        ]);
        $admin->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();

        $this->command?->warn("Admin created: {$email} — change the password after first login.");
    }
}
