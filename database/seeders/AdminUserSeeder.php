<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email', 'admin@dar.rent');
        $password = config('admin.password', 'AdminDar2026!');
        $name = config('admin.name', 'مدير النظام المركزي');

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $admin->forceFill(['is_admin' => true])->save();
    }
}
