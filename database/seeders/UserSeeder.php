<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@cityhall.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'department' => 'Administration',
            'position' => 'Super Administrator',
        ]);

        // Create Admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@cityhall.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'department' => 'IT Department',
            'position' => 'System Administrator',
        ]);
    }
}
