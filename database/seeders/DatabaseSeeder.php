<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AcademicStructureSeeder::class);

        $users = [
            [
                'name' => 'Instructor User',
                'initials' => 'IU',
                'email' => 'instructor@udom.com',
                'role' => 'instructor',
            ],
            [
                'name' => 'Admin User',
                'initials' => 'AU',
                'email' => 'admin@udom.com',
                'role' => 'admin',
            ],
            [
                'name' => 'Student User',
                'initials' => 'SU',
                'email' => 'student@udom.com',
                'role' => 'student',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::query()->firstOrNew(['email' => $userData['email']]);

            $user->forceFill([
                'name' => $userData['name'],
                'initials' => $userData['initials'],
                'email' => $userData['email'],
                'role' => $userData['role'],
                'email_verified_at' => now(),
                'password' => Hash::make('@apes99U'),
                'remember_token' => Str::random(10),
            ])->save();
        }
    }
}
