<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'AP-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'AP-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'AP-B', 'branch_name' => 'Audit Branch']);

        $this->admin = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Hadi Supervisor']);
        $this->admin->assignRole('super_admin');
    }

    private function activities(int $count, string $logName = 'default', string $description = 'Loan application submitted'): void
    {
        $rows = [];
        for ($index = 1; $index <= $count; $index++) {
            $rows[] = [
                'log_name' => $logName,
                'description' => $description.' #'.$index,
                'causer_id' => $this->admin->id,
                'causer_type' => User::class,
                'properties' => '{}',
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ];
        }

        DB::table('activity_log')->insert($rows);
    }

    public function test_the_audit_trail_uses_the_styled_pagination_partial(): void
    {
        $this->activities(30);

        $this->actingAs($this->admin)
            ->get(route('admin.system.audit'))
            ->assertOk()
            ->assertSee('class="pagination"', false)
            ->assertSee('pagination-controls', false)
            ->assertSee('pagination-pages', false)
            ->assertSee(__('Next'))
            ->assertSee(__('Previous'))
            ->assertDontSee('page-link', false);
    }

    public function test_the_audit_trail_paginates_and_navigates_between_pages(): void
    {
        $this->activities(30);

        $first = $this->actingAs($this->admin)->get(route('admin.system.audit'))->assertOk();
        $first->assertSee('Loan application submitted #1');
        $first->assertDontSee('Loan application submitted #30');

        $this->actingAs($this->admin)
            ->get(route('admin.system.audit', ['page' => 2]))
            ->assertOk()
            ->assertSee('Loan application submitted #30')
            ->assertDontSee('Loan application submitted #1');
    }

    public function test_audit_pagination_links_preserve_the_active_filters(): void
    {
        $this->activities(30);

        $this->actingAs($this->admin)
            ->get(route('admin.system.audit', ['log_name' => 'default', 'page' => 2]))
            ->assertOk()
            ->assertSee('log_name=default', false);
    }

    public function test_the_audit_trail_shows_no_pagination_when_results_fit_one_page(): void
    {
        $this->activities(3);

        $this->actingAs($this->admin)
            ->get(route('admin.system.audit'))
            ->assertOk()
            ->assertDontSee('class="pagination"', false);
    }
}
