<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Super Admin
        User::updateOrCreate(
            ['email' => 'superadmin@sppg.com'],
            [
                'name'     => 'Super Admin Utama',
                'password' => Hash::make('password'),
                'role'     => 'super_admin',
                'region'   => 'Pusat',
            ]
        );

        // 2. Akun Admin Dapur/Wilayah
        User::updateOrCreate(
            ['email' => 'admin.harpa2@sppg.com'],
            [
                'name'     => 'Admin Dapur Harpa 2',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'region'   => 'Surabaya',
            ]
        );
    }
}