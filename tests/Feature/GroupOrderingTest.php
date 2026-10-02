<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupOrderingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'GO-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'GO-A']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'GO-B', 'branch_name' => 'Ordering Branch']);

        $this->admin = User::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Hadi Supervisor']);
        $this->admin->assignRole('super_admin');
    }

    private function group(string $name, ?string $day, ?string $time, string $code): MemberGroup
    {
        return MemberGroup::create([
            'branch_id' => $this->branch->id,
            'group_code' => $code,
            'group_name' => $name,
            'meeting_day' => $day,
            'meeting_time' => $time,
        ]);
    }

    public function test_groups_are_ordered_by_meeting_day_then_time_then_name(): void
    {
        $this->group('Zeta', 'Friday', '09:00:00', 'GO-003');
        $this->group('Alpha', 'Monday', '08:00:00', 'GO-001');
        $this->group('Beta', 'Monday', '10:00:00', 'GO-002');
        $this->group('Delta', 'Wednesday', '07:30:00', 'GO-004');

        $response = $this->actingAs($this->admin)->get(route('admin.groups.index'));
        $response->assertOk();

        $response->assertSeeInOrder([
            'Alpha',
            'Beta',
            'Delta',
            'Zeta',
        ]);
    }

    public function test_groups_with_no_meeting_day_appear_after_named_days(): void
    {
        $this->group('After', 'Saturday', '09:00:00', 'GO-A1');
        $this->group('NoDay', null, null, 'GO-ND');
        $this->group('Before', 'Monday', '08:00:00', 'GO-B1');

        $response = $this->actingAs($this->admin)->get(route('admin.groups.index'));
        $response->assertOk();

        $response->assertSeeInOrder([
            'Before',
            'After',
            'NoDay',
        ]);
    }
}
