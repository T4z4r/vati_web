<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardNewMembersTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-06-10 08:00:00';

    private const YESTERDAY = '2026-06-09 08:00:00';

    private User $admin;

    private User $cashier;

    private Branch $branch;

    private Branch $otherBranch;

    private MemberGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'DN-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'DN-A']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DN-B', 'branch_name' => 'Dashboard Branch']);
        $this->otherBranch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DN-C', 'branch_name' => 'Other Branch']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'DN-G', 'group_name' => 'Dashboard Group']);

        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');

        $this->cashier = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashier->assignRole('cashier');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_dashboard_counts_members_registered_today(): void
    {
        Carbon::setTestNow(self::TODAY);
        $this->registerMembers(2, self::TODAY);
        $this->registerMembers(2, self::YESTERDAY);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalMembers', 4)
            ->assertViewHas('newMembersToday', 2)
            ->assertViewHas('newMembersShare', 50.0)
            ->assertSee(__('Members registered today'))
            ->assertSee('50.0%')
            ->assertSee(__('of the member base'));
    }

    public function test_the_daily_operations_statistic_follows_the_branch_filter(): void
    {
        Carbon::setTestNow(self::TODAY);
        $this->registerMembers(2, self::TODAY);
        $this->registerMembers(1, self::YESTERDAY);
        $this->registerMember('DN-OTHER-1', $this->otherBranch, self::TODAY);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['branch_id' => $this->branch->id]))
            ->assertOk()
            ->assertViewHas('totalMembers', 3)
            ->assertViewHas('newMembersToday', 2)
            ->assertViewHas('newMembersShare', 66.7);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalMembers', 4)
            ->assertViewHas('newMembersToday', 3)
            ->assertViewHas('newMembersShare', 75.0);
    }

    public function test_the_statistic_is_visible_on_the_operational_dashboard(): void
    {
        Carbon::setTestNow(self::TODAY);
        $this->registerMembers(2, self::TODAY);
        $this->registerMembers(6, self::YESTERDAY);

        $this->actingAs($this->cashier)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertViewHas('newMembersToday', 2)
            ->assertViewHas('newMembersShare', 25.0)
            ->assertSee(__('Members registered today'));
    }

    public function test_the_statistic_stays_at_zero_when_nobody_was_registered_today(): void
    {
        Carbon::setTestNow(self::TODAY);
        $this->registerMembers(3, self::YESTERDAY);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('newMembersToday', 0)
            ->assertViewHas('newMembersShare', 0.0);
    }

    private function registerMembers(int $count, string $registeredAt): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $this->registerMember('DN-'.substr(md5($registeredAt.$index), 0, 8), $this->branch, $registeredAt);
        }
    }

    private function registerMember(string $membershipNumber, Branch $branch, string $registeredAt): void
    {
        $group = $branch->is($this->branch)
            ? $this->group
            : MemberGroup::firstOrCreate(
                ['branch_id' => $branch->id, 'group_code' => 'DN-G-'.$branch->branch_code],
                ['group_name' => 'Group '.$branch->branch_code]
            );

        Member::create([
            'branch_id' => $branch->id,
            'group_id' => $group->id,
            'membership_number' => $membershipNumber,
            'first_name' => 'Dash',
            'last_name' => 'Member',
            'phone' => '2557'.substr(md5($membershipNumber), 0, 7),
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
        ]);
    }
}
