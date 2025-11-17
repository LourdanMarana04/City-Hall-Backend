<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
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
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@cityhall.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'department_id' => null,
            'position' => 'Super Administrator',
            'is_active' => true,
        ]);

        // Create Admin
        Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@cityhall.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'department_id' => null,
            'position' => 'System Administrator',
            'is_active' => true,
        ]);
    }
}
