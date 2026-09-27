<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanStatusTabsTest extends TestCase
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

        $region = Region::create(['name' => 'Region', 'code' => 'LST-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'LST-A']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'LST-B', 'branch_name' => 'Loan Tabs Branch']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'LST-G', 'group_name' => 'Loan Tabs Group']);
        $this->product = LoanProduct::create(['name' => 'Loan Tabs Product', 'code' => 'LST-P', 'interest_method' => 'flat', 'minimum_amount' => 1000, 'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 24, 'annual_interest_rate' => 10]);
        $this->member = Member::create([
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'membership_number' => 'LST-M001',
            'first_name' => 'Loan',
            'last_name' => 'Tab',
            'phone' => '255720000001',
            'created_by' => $this->admin->id,
        ]);
    }

    private function loan(string $number, LoanStatus $status, ?Branch $branch = null): Loan
    {
        $application = LoanApplication::create([
            'application_number' => 'LST-APP-'.$number,
            'member_id' => $this->member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $this->group->id,
            'branch_id' => ($branch ?? $this->branch)->id,
            'requested_amount' => 100000,
            'duration_months' => 6,
            'status' => ApplicationStatus::APPROVED,
            'created_by' => $this->admin->id,
        ]);

        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $this->member->id,
            'group_id' => $this->group->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => ($branch ?? $this->branch)->id,
            'principal_amount' => 100000,
            'interest_amount' => 10000,
            'total_repayment' => 110000,
            'principal_balance' => 100000,
            'interest_balance' => 10000,
            'total_balance' => 110000,
            'number_of_installments' => 6,
            'installment_amount' => 18333,
            'status' => $status,
        ]);
    }

    public function test_it_renders_a_tab_for_every_loan_status_with_counts(): void
    {
        $this->loan('LST-L1', LoanStatus::ACTIVE);
        $this->loan('LST-L2', LoanStatus::OVERDUE);
        $this->loan('LST-L3', LoanStatus::OVERDUE);

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index'))
            ->assertOk()
            ->assertViewHas('activeStatus', '')
            ->assertViewHas('statusTabs', function (array $tabs) {
                $this->assertSame('', $tabs[0]['key']);
                $this->assertSame(3, $tabs[0]['count']);
                $this->assertSame(count(LoanStatus::cases()) + 1, count($tabs));

                $keys = array_column($tabs, 'key');
                foreach (LoanStatus::cases() as $case) {
                    $this->assertContains($case->value, $keys);
                }

                $counts = array_column($tabs, 'count', 'key');
                $this->assertSame(1, $counts['active']);
                $this->assertSame(2, $counts['overdue']);
                $this->assertSame(0, $counts['settled']);
                $this->assertSame(3, array_sum(array_filter($counts, fn ($k) => $k !== '', ARRAY_FILTER_USE_KEY)));

                return true;
            })
            ->assertSee('status=overdue', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_a_status_tab_filters_the_list_and_is_marked_active(): void
    {
        $settled = $this->loan('LST-L1', LoanStatus::SETTLED);
        $this->loan('LST-L2', LoanStatus::ACTIVE);
        $this->loan('LST-L3', LoanStatus::PENDING_DISBURSEMENT);

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index', ['status' => 'settled']))
            ->assertOk()
            ->assertViewHas('activeStatus', 'settled')
            ->assertViewHas('loans', fn ($paginator) => $paginator->total() === 1 && $paginator->first()->id === $settled->id)
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_active_tab_keeps_other_filters_and_resets_the_page(): void
    {
        $this->loan('LST-L1', LoanStatus::ACTIVE);

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index', ['search' => 'LST-L1', 'status' => 'active', 'page' => 3]))
            ->assertOk()
            ->assertViewHas('activeStatus', 'active')
            ->assertViewHas('loans', fn ($paginator) => $paginator->total() === 1)
            ->assertSee('href="'.e(route('admin.loans.index', ['search' => 'LST-L1', 'status' => 'active'])).'"', false)
            ->assertSee('href="'.e(route('admin.loans.index', ['search' => 'LST-L1'])).'"', false)
            ->assertDontSee(e(route('admin.loans.index', ['search' => 'LST-L1', 'status' => 'active', 'page' => 3])), false);
    }

    public function test_an_unknown_status_falls_back_to_all_loans(): void
    {
        $this->loan('LST-L1', LoanStatus::ACTIVE);

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index', ['status' => 'not-a-status']))
            ->assertOk()
            ->assertViewHas('activeStatus', '')
            ->assertViewHas('loans', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_tab_counts_respect_the_branch_scope_of_the_user(): void
    {
        $otherBranch = Branch::create(['area_id' => $this->branch->area_id, 'branch_code' => 'LST-B2', 'branch_name' => 'Other Branch']);
        $this->loan('LST-L1', LoanStatus::ACTIVE);
        $this->loan('LST-L2', LoanStatus::ACTIVE, $otherBranch);

        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->assignRole('branch_manager');

        $this->actingAs($manager)
            ->get(route('admin.loans.index'))
            ->assertOk()
            ->assertViewHas('loans', fn ($paginator) => $paginator->total() === 1)
            ->assertViewHas('statusTabs', function (array $tabs) {
                $counts = array_column($tabs, 'count', 'key');

                $this->assertSame(1, $counts['active']);
                $this->assertSame(1, $counts['']);

                return true;
            });
    }
}
