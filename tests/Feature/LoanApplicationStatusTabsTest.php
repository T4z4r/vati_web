<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanApplicationStatusTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Member $member;

    private LoanProduct $product;

    private MemberGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['branch_id' => null]);
        $this->admin->assignRole('super_admin');

        $region = Region::create(['name' => 'Region', 'code' => 'TAB-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'TAB-A']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'TAB-B', 'branch_name' => 'Tabs Branch']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'TAB-G', 'group_name' => 'Tabs Group']);
        $this->product = LoanProduct::create(['name' => 'Tabs Product', 'code' => 'TAB-P', 'interest_method' => 'flat', 'minimum_amount' => 1000, 'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 24, 'annual_interest_rate' => 10]);
        $this->member = Member::create([
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'membership_number' => 'TAB-M001',
            'first_name' => 'Tab',
            'last_name' => 'Member',
            'phone' => '255710000001',
            'created_by' => $this->admin->id,
        ]);
    }

    private function application(string $number, ApplicationStatus $status, ?Branch $branch = null, ?Member $member = null): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => $number,
            'member_id' => ($member ?? $this->member)->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $this->group->id,
            'branch_id' => ($branch ?? $this->branch)->id,
            'requested_amount' => 100000,
            'duration_months' => 6,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_it_renders_a_tab_for_every_application_status_with_counts(): void
    {
        $this->application('TAB-A-1', ApplicationStatus::DRAFT);
        $this->application('TAB-A-2', ApplicationStatus::APPROVED);
        $this->application('TAB-A-3', ApplicationStatus::APPROVED);

        $this->actingAs($this->admin)
            ->get(route('admin.loan-applications.index'))
            ->assertOk()
            ->assertViewHas('activeStatus', '')
            ->assertViewHas('statusTabs', function (array $tabs) {
                $this->assertSame('', $tabs[0]['key']);
                $this->assertSame(3, $tabs[0]['count']);

                $keys = array_column($tabs, 'key');
                $this->assertSame(ApplicationStatus::cases(), array_values(array_filter(ApplicationStatus::cases(), fn ($case) => in_array($case->value, $keys, true))));

                $counts = array_column($tabs, 'count', 'key');
                $this->assertSame(1, $counts['draft']);
                $this->assertSame(2, $counts['approved']);
                $this->assertSame(0, $counts['disbursed']);
                $this->assertSame(3, array_sum(array_filter($counts, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY)));
                $this->assertSame(count(ApplicationStatus::cases()) + 1, count($tabs));

                return true;
            })
            ->assertSee('status=approved', false)
            ->assertSee('status=lo_review', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_a_status_tab_filters_the_list_and_is_marked_active(): void
    {
        $approved = $this->application('TAB-A-1', ApplicationStatus::APPROVED);
        $this->application('TAB-A-2', ApplicationStatus::DRAFT);
        $this->application('TAB-A-3', ApplicationStatus::REJECTED);

        $this->actingAs($this->admin)
            ->get(route('admin.loan-applications.index', ['status' => 'approved']))
            ->assertOk()
            ->assertViewHas('activeStatus', 'approved')
            ->assertViewHas('applications', fn ($paginator) => $paginator->total() === 1 && $paginator->first()->id === $approved->id)
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_active_tab_keeps_other_filters_and_resets_the_page(): void
    {
        $this->application('TAB-A-1', ApplicationStatus::APPROVED);

        $this->actingAs($this->admin)
            ->get(route('admin.loan-applications.index', ['search' => 'TAB-A-1', 'status' => 'approved', 'page' => 3]))
            ->assertOk()
            ->assertViewHas('activeStatus', 'approved')
            ->assertViewHas('applications', fn ($paginator) => $paginator->total() === 1)
            ->assertSee('href="'.e(route('admin.loan-applications.index', ['search' => 'TAB-A-1', 'status' => 'approved'])).'"', false)
            ->assertSee('href="'.e(route('admin.loan-applications.index', ['search' => 'TAB-A-1'])).'"', false)
            ->assertDontSee(e(route('admin.loan-applications.index', ['search' => 'TAB-A-1', 'status' => 'approved', 'page' => 3])), false);
    }

    public function test_an_unknown_status_falls_back_to_all_applications(): void
    {
        $this->application('TAB-A-1', ApplicationStatus::APPROVED);

        $this->actingAs($this->admin)
            ->get(route('admin.loan-applications.index', ['status' => 'not-a-status']))
            ->assertOk()
            ->assertViewHas('activeStatus', null)
            ->assertViewHas('applications', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_tab_counts_respect_the_branch_scope_of_the_user(): void
    {
        $otherBranch = Branch::create(['area_id' => $this->branch->area_id, 'branch_code' => 'TAB-B2', 'branch_name' => 'Other Branch']);
        $this->application('TAB-A-1', ApplicationStatus::APPROVED);
        $this->application('TAB-A-2', ApplicationStatus::APPROVED, $otherBranch);

        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->assignRole('branch_manager');

        $this->actingAs($manager)
            ->get(route('admin.loan-applications.index'))
            ->assertOk()
            ->assertViewHas('applications', fn ($paginator) => $paginator->total() === 1)
            ->assertViewHas('statusTabs', function (array $tabs) {
                $counts = array_column($tabs, 'count', 'key');

                $this->assertSame(1, $counts['approved']);
                $this->assertSame(1, $counts['']);

                return true;
            });
    }
}
