<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-super-admin 
                            {--email=superadmin@evergreen.com : Email address for super admin}
                            {--password=password : Password for super admin}
                            {--reset : Reset password if user already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update super admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $reset = $this->option('reset');

        // Check if super admin already exists
        $superAdmin = User::withoutGlobalScopes()
            ->where('email', $email)
            ->first();

        if ($superAdmin) {
            if ($reset) {
                $superAdmin->password = Hash::make($password);
                $superAdmin->role = 'super_admin';
                $superAdmin->is_active = true;
                $superAdmin->facility_id = null;
                $superAdmin->save();

                $this->info("✅ Super admin password reset successfully!");
                $this->line("   Email: {$email}");
                $this->line("   Password: {$password}");
            } else {
                $this->warn("⚠️  Super admin user already exists with email: {$email}");
                $this->line("   Use --reset flag to reset the password");
                $this->line("   Current role: {$superAdmin->role}");
                $this->line("   Is active: " . ($superAdmin->is_active ? 'Yes' : 'No'));
                return 1;
            }
        } else {
            // Create new super admin
            $superAdmin = User::create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
                'facility_id' => null, // Super admins don't belong to a facility
            ]);

            $this->info("✅ Super admin user created successfully!");
            $this->line("   Email: {$email}");
            $this->line("   Password: {$password}");
        }

        $this->newLine();
        $this->warn("⚠️  SECURITY WARNING: Change this password immediately in production!");

        return 0;
    }
}

