<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'user:create-admin 
                            {--name=System Administrator : Admin full name}
                            {--email=admin@udom.com : Admin email}
                            {--password=@AdminPass123 : Admin password}';

    protected $description = 'Create a single admin user';

    public function handle(): int
    {
        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return 1;
        }

        $admin = User::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info('✓ Admin user created successfully!');
        $this->info("  Email: {$email}");
        $this->info("  Password: {$password}");
        $this->info("  ID: {$admin->id}");

        return 0;
    }
}
