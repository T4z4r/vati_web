<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, LoanProductSeeder::class, LoanTermSeeder::class, PolicySeeder::class]);

        if (env('VATI_ADMIN_EMAIL') && env('VATI_ADMIN_PASSWORD')) {
            $admin = User::updateOrCreate(['email' => env('VATI_ADMIN_EMAIL')], ['name' => 'VATI Super Administrator', 'password' => Hash::make(env('VATI_ADMIN_PASSWORD')), 'status' => true]);
            $admin->syncRoles('super_admin');
        }
    }
}
