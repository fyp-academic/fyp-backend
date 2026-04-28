<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'id' => Str::uuid()->toString(),
            'name' => 'System Administrator',
            'email' => 'admin@udom.com',
            'password' => Hash::make('@AdminPass123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@udom.com');
        $this->command->info('Password: @AdminPass123');
    }
}
