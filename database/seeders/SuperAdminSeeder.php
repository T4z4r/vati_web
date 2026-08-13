<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'super.admin@vati.co.tz';

    public const DEFAULT_PASSWORD = 'Admin@Vati2026!';

    public function run(): void
    {
        if (! Role::where('name', 'super_admin')->where('guard_name', 'web')->exists()) {
            $this->call(RolePermissionSeeder::class);
        }

        $email = env('VATI_ADMIN_EMAIL') ?: self::DEFAULT_EMAIL;
        $password = env('VATI_ADMIN_PASSWORD') ?: self::DEFAULT_PASSWORD;

        if (app()->environment('production') && (! env('VATI_ADMIN_EMAIL') || ! env('VATI_ADMIN_PASSWORD'))) {
            throw new RuntimeException('Set VATI_ADMIN_EMAIL and VATI_ADMIN_PASSWORD before seeding a production super administrator.');
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'VATI Super Administrator',
                'branch_id' => null,
                'password' => Hash::make($password),
                'status' => true,
            ]
        );

        $admin->syncRoles('super_admin');

        $this->command?->info("Super administrator ready: {$email}");
    }
}
