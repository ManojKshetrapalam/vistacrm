<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'crm:create-admin 
                            {--username=admin : Administrator username}
                            {--email=admin@vistaboundintegrated.com : Administrator email address}
                            {--password= : Optional custom password, defaults to temporary bootstrap Admin123}
                            {--force : Force update password if administrator account already exists}';

    protected $description = 'Safely create or verify the initial CRM administrator account';

    public function handle(): int
    {
        $username = $this->option('username') ?: 'admin';
        $email = $this->option('email') ?: 'admin@vistaboundintegrated.com';
        $force = $this->option('force');
        $inputPassword = $this->option('password') ?: 'Admin123';

        // Check if an admin user already exists by username or email
        $existingAdmin = User::where('role', 'admin')
            ->where(function ($q) use ($username, $email) {
                $q->where('username', $username)
                  ->orWhere('email', $email);
            })
            ->first();

        if ($existingAdmin) {
            $this->info("An existing administrator account was found (ID: {$existingAdmin->id}, Email: {$existingAdmin->email}).");

            // Ensure username is attached if not present
            if (empty($existingAdmin->username)) {
                $existingAdmin->username = $username;
                $existingAdmin->save();
                $this->info("Associated username '{$username}' with existing administrator account.");
            }

            if (!$force) {
                $this->warn("Administrator credentials were preserved. Use --force to reset the password.");
                return Command::SUCCESS;
            }

            // Force update password securely
            $existingAdmin->password = Hash::make($inputPassword);
            $existingAdmin->must_change_password = true;
            $existingAdmin->is_active = true;
            $existingAdmin->save();

            $this->info("Successfully reset administrator password. Password change will be required upon next login.");
            return Command::SUCCESS;
        }

        // Create initial administrator account securely
        $newAdmin = User::create([
            'name' => 'CRM Administrator',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($inputPassword),
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => true,
            'notification_preferences' => [
                'in_app' => true,
                'whatsapp' => true,
                'email' => false,
            ],
        ]);

        $this->info("Initial administrator account created successfully.");
        $this->info("Username: {$newAdmin->username}");
        $this->info("Email: {$newAdmin->email}");
        $this->info("Role: {$newAdmin->role}");
        $this->info("Status: Active");
        $this->info("Password change required on first login: Yes");

        return Command::SUCCESS;
    }
}
