<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'NAV-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'NAV-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'NAV-B', 'branch_name' => 'Navbar Branch']);

        $this->admin = User::factory()->create(['branch_id' => $branch->id]);
        $this->admin->assignRole('super_admin');
    }

    public function test_the_topbar_exposes_a_refresh_link(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.e(route('admin.dashboard')).'"', false)
            ->assertSee('title="Refresh"', false)
            ->assertSee('aria-label="Refresh page"', false)
            ->assertSee('ph-arrow-clockwise', false);
    }

    public function test_the_refresh_link_keeps_the_current_filters_and_tab(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.loans.index', ['status' => 'active', 'search' => 'LST', 'page' => 2]))
            ->assertOk()
            ->assertSee('href="'.e(route('admin.loans.index', ['page' => 2, 'search' => 'LST', 'status' => 'active'])).'"', false);
    }
}
