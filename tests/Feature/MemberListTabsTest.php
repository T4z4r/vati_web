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

class MemberListTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MemberGroup $group;

    private LoanProduct $product;

    private Member $withLoanActive;

    private Member $withLoanActiveTwo;

    private Member $withLoanInactive;

    private Member $withoutLoan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['branch_id' => null]);
        $this->admin->assignRole('super_admin');

        $region = Region::create(['name' => 'Region', 'code' => 'MLT-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'MLT-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'MLT-B', 'branch_name' => 'Tabs Branch']);
        $this->group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'MLT-G', 'group_name' => 'Tabs Group']);
        $this->product = LoanProduct::create([
            'name' => 'Tabs Product',
            'code' => 'MLT-P',
            'interest_method' => 'flat',
            'minimum_amount' => 1000,
            'maximum_amount' => 5000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 24,
            'annual_interest_rate' => 10,
        ]);

        $this->withLoanActive = $this->member('MLT-M1', 'Asha', 'active');
        $this->withLoanActiveTwo = $this->member('MLT-M2', 'Bakari', 'active');
        $this->withLoanInactive = $this->member('MLT-M3', 'Chama', 'inactive');
        $this->withoutLoan = $this->member('MLT-M4', 'Dogo', 'active');

        $this->loan('MLT-L1', $this->withLoanActive);
        $this->loan('MLT-L2', $this->withLoanActiveTwo);
        $this->loan('MLT-L3', $this->withLoanInactive);
    }

    private function member(string $number, string $firstName, string $status): Member
    {
        return Member::create([
            'branch_id' => $this->group->branch_id,
            'group_id' => $this->group->id,
            'membership_number' => $number,
            'first_name' => $firstName,
            'last_name' => 'Member',
            'phone' => '2557'.substr(md5($number), 0, 7),
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function loan(string $number, Member $member): Loan
    {
        $application = LoanApplication::create([
            'application_number' => 'APP-'.$number,
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $this->group->id,
            'branch_id' => $member->branch_id,
            'requested_amount' => 1000,
            'duration_months' => 6,
            'status' => ApplicationStatus::DISBURSED,
            'created_by' => $this->admin->id,
        ]);

        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $member->id,
            'group_id' => $this->group->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => $member->branch_id,
            'principal_amount' => 1000,
            'interest_amount' => 200,
            'total_repayment' => 1200,
            'principal_balance' => 1000,
            'interest_balance' => 200,
            'total_balance' => 1200,
            'number_of_installments' => 1,
            'installment_amount' => 1200,
            'status' => LoanStatus::ACTIVE->value,
        ]);
    }

    public function test_member_tabs_report_all_with_loan_and_without_loan_counts(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index'))
            ->assertOk()
            ->assertViewHas('loanTabs', fn ($tabs) => collect($tabs)->pluck('count', 'key')->get('') === 4
                && collect($tabs)->pluck('count', 'key')->get('with') === 3
                && collect($tabs)->pluck('count', 'key')->get('without') === 1)
            ->assertSee(__('All members'))
            ->assertSee(__('With loan'))
            ->assertSee(__('Without loan'));
    }

    public function test_the_all_tab_keeps_every_member(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index'))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->total() === 4)
            ->assertViewHas('activeLoanTab', '');
    }

    public function test_the_with_loan_tab_limits_the_list_to_members_that_have_a_loan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['loan' => 'with']))
            ->assertOk()
            ->assertViewHas('activeLoanTab', 'with')
            ->assertViewHas('members', fn ($members) => $members->total() === 3
                && $members->pluck('id')->doesntContain($this->withoutLoan->id));
    }

    public function test_the_without_loan_tab_limits_the_list_to_members_without_a_loan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['loan' => 'without']))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->total() === 1 && $members->first()->is($this->withoutLoan));
    }

    public function test_tab_counts_respect_the_active_status_filter(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['status' => 'active']))
            ->assertOk()
            ->assertViewHas('loanTabs', fn ($tabs) => collect($tabs)->pluck('count', 'key')->get('') === 3
                && collect($tabs)->pluck('count', 'key')->get('with') === 2
                && collect($tabs)->pluck('count', 'key')->get('without') === 1);
    }

    public function test_tab_links_keep_the_active_status_filter(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('status=active', false)
            ->assertSee('loan=with', false)
            ->assertSee('loan=without', false);
    }

    public function test_an_unknown_loan_tab_falls_back_to_all_members(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['loan' => 'bogus']))
            ->assertOk()
            ->assertViewHas('activeLoanTab', '')
            ->assertViewHas('members', fn ($members) => $members->total() === 4);
    }

    public function test_the_active_tab_is_marked_current(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['loan' => 'with']))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }
}
