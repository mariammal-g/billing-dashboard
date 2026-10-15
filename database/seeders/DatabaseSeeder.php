<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\UserRoleMapping;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    { 
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Administrator with full access',
            ],
            [
                'name' => 'User',
                'description' => 'Regular user with limited access',
            ],
            [
                'name' => 'Moderator',
                'description' => 'Moderator with moderate access',
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
        // Create a default admin user
        $adminUser = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@trivent.com',
            'password' => bcrypt('Nframes@123'),
            'status' => true,
            'email_verified_at' => now(),
            'attempts' => 0,
            'last_attempt_at' => null,
        ]);

        // Assign admin role to the user
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            UserRoleMapping::create([
                'user_id' => $adminUser->id,
                'role_id' => $adminRole->id,
            ]);
        }
    }
}
