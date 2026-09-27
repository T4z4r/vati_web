<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupVisit;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ListingStatisticsCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Branch $otherBranch;

    private MemberGroup $group;

    private MemberGroup $otherGroup;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-10 08:00:00');

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['branch_id' => null]);
        $this->admin->assignRole('super_admin');

        $region = Region::create(['name' => 'Region', 'code' => 'LSC-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'LSC-A']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'LSC-B', 'branch_name' => 'Stats Branch']);
        $this->otherBranch = Branch::create(['area_id' => $area->id, 'branch_code' => 'LSC-C', 'branch_name' => 'Other Stats Branch']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'LSC-G', 'group_name' => 'Stats Group']);
        $this->otherGroup = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'LSC-G2', 'group_name' => 'Second Stats Group']);
        $this->product = LoanProduct::create([
            'name' => 'Stats Product',
            'code' => 'LSC-P',
            'interest_method' => 'flat',
            'minimum_amount' => 1000,
            'maximum_amount' => 5000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 24,
            'annual_interest_rate' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_members_listing_summarises_the_filtered_set(): void
    {
        $this->member('LSC-M1', 'Asha', 'active', $this->group, '2026-06-10 07:00:00');
        $this->member('LSC-M2', 'Bakari', 'active', $this->otherGroup, '2026-06-01 07:00:00');
        $this->member('LSC-M3', 'Chama', 'inactive', $this->group, '2026-06-02 07:00:00');
        $this->member('LSC-M4', 'Dogo', 'active', $this->otherGroup, '2026-06-03 07:00:00');

        $this->actingAs($this->admin)
            ->get(route('admin.members.index'))
            ->assertOk()
            ->assertViewIs('admin.members.index')
            ->assertViewHas('stats.total', 4)
            ->assertViewHas('stats.active', 3)
            ->assertViewHas('stats.activeShare', 75.0)
            ->assertViewHas('stats.newToday', 1)
            ->assertViewHas('stats.groups', 2)
            ->assertSee(__('Groups covered'));

        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['search' => 'Asha']))
            ->assertOk()
            ->assertViewHas('stats.total', 1)
            ->assertViewHas('stats.newToday', 1)
            ->assertViewHas('stats.activeShare', 100.0)
            ->assertViewHas('stats.groups', 1);

        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['group_id' => $this->otherGroup->id]))
            ->assertOk()
            ->assertViewHas('stats.total', 2)
            ->assertViewHas('stats.newToday', 0);
    }

    public function test_the_group_listing_counts_members_and_loans(): void
    {
        $member = $this->member('LSC-GM1', 'Eli', 'active', $this->group);
        $this->member('LSC-GM2', 'Femi', 'active', $this->group);
        $this->member('LSC-GM3', 'Gigi', 'active', $this->otherGroup);
        $this->loan('LSC-L1', $member);
        $this->loan('LSC-L2', $member, $this->otherGroup);
        MemberGroup::find($this->otherGroup->id)->update(['status' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.groups.index'))
            ->assertOk()
            ->assertViewHas('stats.total', 2)
            ->assertViewHas('stats.active', 1)
            ->assertViewHas('stats.activeShare', 50.0)
            ->assertViewHas('stats.members', 3)
            ->assertViewHas('stats.loans', 2)
            ->assertSee(__('Active groups'));
    }

    public function test_the_group_visits_listing_summarises_the_period(): void
    {
        $this->visit('2026-06-10', $this->group);
        $this->visit('2026-06-04', $this->group);
        $this->visit('2026-05-20', $this->otherGroup);

        $this->actingAs($this->admin)
            ->get(route('admin.group-visits.index'))
            ->assertOk()
            ->assertViewHas('stats.total', 3)
            ->assertViewHas('stats.thisMonth', 2)
            ->assertViewHas('stats.today', 1)
            ->assertViewHas('stats.groups', 2)
            ->assertSee(__('Groups visited'));

        $this->actingAs($this->admin)
            ->get(route('admin.group-visits.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertViewHas('stats.total', 2)
            ->assertViewHas('stats.thisMonth', 2)
            ->assertViewHas('stats.groups', 1);
    }

    public function test_the_applications_listing_keeps_totals_across_the_status_tab(): void
    {
        $member = $this->member('LSC-AM1', 'Hadi', 'active', $this->group);
        $this->application('LSC-A1', ApplicationStatus::SUBMITTED, $member, 100000);
        $this->application('LSC-A2', ApplicationStatus::LO_REVIEW, $member, 150000);
        $this->application('LSC-A3', ApplicationStatus::APPROVED, $member, 200000);
        $this->application('LSC-A4', ApplicationStatus::REJECTED, $member, 50000);

        $this->actingAs($this->admin)
            ->get(route('admin.loan-applications.index'))
            ->assertOk()
            ->assertViewHas('stats.total', 4)
            ->assertViewHas('stats.inReview', 2)
            ->assertViewHas('stats.approved', 1)
            ->assertViewHas('stats.requested', 500000.0)
            ->assertSee(__('Amount requested'));

        $this->actingAs($this->admin)
            ->get(route('admin.loan-applications.index', ['status' => 'approved']))
            ->assertOk()
            ->assertViewHas('applications', fn ($paginator) => $paginator->total() === 1)
            ->assertViewHas('stats.total', 4)
            ->assertViewHas('stats.inReview', 2)
            ->assertViewHas('stats.requested', 500000.0);
    }

    public function test_the_loans_listing_reports_portfolio_totals(): void
    {
        $member = $this->member('LSC-LM1', 'Imani', 'active', $this->group);
        $this->loan('LSC-P1', $member, null, 1000, 0, 0);
        $this->loan('LSC-P2', $member, null, 2000, 1500, 1800);

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index'))
            ->assertOk()
            ->assertViewHas('stats.total', 2)
            ->assertViewHas('stats.disbursed', 3000.0)
            ->assertViewHas('stats.outstanding', 1800.0)
            ->assertViewHas('stats.repaidShare', 50.0)
            ->assertSee(__('Outstanding balance'));

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index', ['status' => 'settled']))
            ->assertOk()
            ->assertViewHas('loans', fn ($paginator) => $paginator->total() === 0)
            ->assertViewHas('stats.total', 2)
            ->assertViewHas('stats.repaidShare', 50.0);
    }

    public function test_empty_listings_report_zero_percentages(): void
    {
        $empty = Branch::create(['area_id' => $this->branch->area_id, 'branch_code' => 'LSC-X', 'branch_name' => 'Empty Branch']);

        foreach (['admin.members.index', 'admin.groups.index', 'admin.group-visits.index', 'admin.loan-applications.index', 'admin.loans.index'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route, ['branch_id' => $empty->id]))
                ->assertOk()
                ->assertViewHas('stats.total', 0);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['branch_id' => $empty->id]))
            ->assertViewHas('stats.activeShare', 0.0);

        $this->actingAs($this->admin)
            ->get(route('admin.loans.index', ['branch_id' => $empty->id]))
            ->assertViewHas('stats.repaidShare', 0.0);
    }

    private function member(string $number, string $firstName, string $status, MemberGroup $group, ?string $createdAt = null): Member
    {
        $data = [
            'branch_id' => $group->branch_id,
            'group_id' => $group->id,
            'membership_number' => $number,
            'first_name' => $firstName,
            'last_name' => 'Member',
            'phone' => '2557'.substr(md5($number), 0, 7),
            'status' => $status,
            'created_by' => $this->admin->id,
        ];

        if ($createdAt) {
            $data['created_at'] = $createdAt;
            $data['updated_at'] = $createdAt;
        }

        return Member::create($data);
    }

    private function loan(string $number, Member $member, ?MemberGroup $group = null, int $principal = 1000, int $principalBalance = 1000, int $totalBalance = 1200): Loan
    {
        $application = LoanApplication::create([
            'application_number' => 'APP-'.$number,
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => ($group ?? $this->group)->id,
            'branch_id' => $member->branch_id,
            'requested_amount' => $principal,
            'duration_months' => 6,
            'status' => ApplicationStatus::DISBURSED,
            'created_by' => $this->admin->id,
        ]);

        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $member->id,
            'group_id' => ($group ?? $this->group)->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => $member->branch_id,
            'principal_amount' => $principal,
            'interest_amount' => 200,
            'total_repayment' => $principal + 200,
            'principal_balance' => $principalBalance,
            'interest_balance' => max(0, $totalBalance - $principalBalance),
            'total_balance' => $totalBalance,
            'number_of_installments' => 1,
            'installment_amount' => $principal + 200,
            'status' => LoanStatus::ACTIVE->value,
        ]);
    }

    private function application(string $number, ApplicationStatus $status, Member $member, int $amount): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => $number,
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $member->group_id,
            'branch_id' => $member->branch_id,
            'requested_amount' => $amount,
            'duration_months' => 6,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function visit(string $date, MemberGroup $group): GroupVisit
    {
        return GroupVisit::create([
            'group_id' => $group->id,
            'user_id' => $this->admin->id,
            'visit_date' => $date,
        ]);
    }
}
