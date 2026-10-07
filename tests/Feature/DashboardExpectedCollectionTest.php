<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardExpectedCollectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    private MemberGroup $group;

    private Member $member;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'EXP-001', 'branch_name' => 'Expected']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'EXP-G01', 'group_name' => 'Expected Group']);
        $this->member = Member::create([
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'membership_number' => 'EXP-M01',
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255711111120',
        ]);
        $this->product = LoanProduct::create([
            'name' => 'Weekly Loan',
            'code' => 'WEEKLY',
            'minimum_amount' => 100000,
            'maximum_amount' => 5000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 12,
            'repayment_frequency' => 'weekly',
            'vat_percentage' => 0.18,
        ]);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');
    }

    private function loan(string $number, string $status = 'active', ?Branch $branch = null, ?MemberGroup $group = null, ?Member $member = null): Loan
    {
        $branch ??= $this->branch;
        $group ??= $this->group;
        $member ??= $this->member;
        $application = LoanApplication::create([
            'application_number' => $number.'-LAF',
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $group->id,
            'branch_id' => $branch->id,
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $member->id,
            'group_id' => $group->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => $branch->id,
            'principal_amount' => 1000000,
            'interest_amount' => 0,
            'total_repayment' => 1000000,
            'principal_balance' => 1000000,
            'interest_balance' => 0,
            'total_balance' => 1000000,
            'number_of_installments' => 6,
            'installment_amount' => 166666.67,
            'first_payment_date' => today()->subMonths(6),
            'status' => $status,
        ]);
    }

    private function installment(Loan $loan, int $n, string $dueDate, float $totalDue, array $overrides = []): LoanInstallment
    {
        return $loan->installments()->create(array_merge([
            'installment_number' => $n,
            'due_date' => $dueDate,
            'principal_due' => $totalDue,
            'interest_due' => 0,
            'total_due' => $totalDue,
            'outstanding_balance' => $totalDue,
            'status' => 'upcoming',
        ], $overrides));
    }

    public function test_expected_collection_counts_the_given_day_but_not_arrears_or_future(): void
    {
        $loan = $this->loan('EXP-L-1');
        $this->installment($loan, 1, today()->subDays(7)->toDateString(), 100000, ['status' => 'overdue']);
        $this->installment($loan, 2, today()->toDateString(), 50000);
        $this->installment($loan, 3, today()->addDay()->toDateString(), 80000);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 50000.0);
    }

    public function test_expected_collection_uses_east_african_day_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 00:30:00', config('app.timezone')));

        try {
            $loan = $this->loan('EXP-L-1B');
            $this->installment($loan, 1, '2026-07-09', 100000, ['status' => 'overdue']);
            $this->installment($loan, 2, '2026-07-10', 50000);

            $this->actingAs($this->admin)
                ->get(route('admin.dashboard'))
                ->assertOk()
                ->assertViewHas('expected', 50000.0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expected_collection_can_use_a_requested_collection_date(): void
    {
        $loan = $this->loan('EXP-L-1C');
        $this->installment($loan, 1, '2026-07-09', 100000);
        $this->installment($loan, 2, '2026-07-10', 50000);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['collection_date' => '2026-07-09']))
            ->assertOk()
            ->assertViewHas('expected', 100000.0);
    }

    public function test_dashboard_expected_card_links_to_expected_repayments_for_the_day(): void
    {
        $loan = $this->loan('EXP-L-1D');
        $this->installment($loan, 1, today()->toDateString(), 100000);

        $url = route('admin.repayments.expected', ['collection_date' => today()->toDateString()]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($url, false);
    }

    public function test_expected_repayments_page_lists_scheduled_installments_and_statuses(): void
    {
        $loan = $this->loan('EXP-L-1E');
        $this->installment($loan, 1, today()->toDateString(), 100000);
        $this->installment($loan, 2, today()->toDateString(), 50000, ['total_paid' => 20000, 'status' => 'partially_paid']);
        $this->installment($loan, 3, today()->addDay()->toDateString(), 75000);

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.expected', ['collection_date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('EXP-L-1E')
            ->assertSee('partially paid')
            ->assertSee('TZS 150,000')
            ->assertSee('TZS 20,000.00')
            ->assertSee('TZS 130,000.00')
            ->assertSee('2 installments')
            ->assertSee('1 borrower')
            ->assertSee('people expected to repay')
            ->assertDontSee('TZS 75,000.00');
    }

    public function test_expected_collection_excludes_fully_paid_installments(): void
    {
        $loan = $this->loan('EXP-L-2');
        $this->installment($loan, 1, today()->toDateString(), 60000, ['total_paid' => 60000, 'status' => 'paid']);
        $this->installment($loan, 2, today()->subDays(5)->toDateString(), 40000, ['total_paid' => 40000, 'status' => 'paid']);
        $this->installment($loan, 3, today()->subDays(9)->toDateString(), 30000, ['status' => 'waived']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 0.0);
    }

    public function test_expected_collection_is_net_of_payments_and_interest_exemptions(): void
    {
        $loan = $this->loan('EXP-L-3');
        $this->installment($loan, 1, today()->toDateString(), 100000, ['total_paid' => 30000, 'principal_paid' => 30000, 'status' => 'partially_paid']);
        $this->installment($loan, 2, today()->subDays(3)->toDateString(), 50000, ['interest_exemption' => 5000, 'status' => 'overdue']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 70000.0);
    }

    public function test_expected_collection_ignores_installments_on_loans_that_are_not_active_or_overdue(): void
    {
        $settled = $this->loan('EXP-L-4', 'settled');
        $this->installment($settled, 1, today()->toDateString(), 90000);

        $cancelled = $this->loan('EXP-L-5', 'cancelled');
        $this->installment($cancelled, 1, today()->toDateString(), 70000);

        $active = $this->loan('EXP-L-6');
        $this->installment($active, 1, today()->subDays(2)->toDateString(), 25000, ['status' => 'overdue']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 0.0);
    }

    public function test_expected_collection_respects_branch_scope(): void
    {
        $loan = $this->loan('EXP-L-7');
        $this->installment($loan, 1, today()->toDateString(), 200000);

        $otherRegion = Region::create(['name' => 'Mwanza', 'code' => 'MWZ']);
        $otherArea = Area::create(['region_id' => $otherRegion->id, 'name' => 'Ilemela', 'code' => 'ILM']);
        $otherBranch = Branch::create(['area_id' => $otherArea->id, 'branch_code' => 'EXP-002', 'branch_name' => 'Other']);
        $otherGroup = MemberGroup::create(['branch_id' => $otherBranch->id, 'group_code' => 'EXP-G02', 'group_name' => 'Other Group']);
        $otherMember = Member::create([
            'branch_id' => $otherBranch->id,
            'group_id' => $otherGroup->id,
            'membership_number' => 'EXP-M02',
            'first_name' => 'Juma',
            'last_name' => 'Ali',
            'phone' => '255711111121',
        ]);
        $otherLoan = $this->loan('EXP-L-8', 'active', $otherBranch, $otherGroup, $otherMember);
        $this->installment($otherLoan, 1, today()->toDateString(), 600000);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['branch_id' => $otherBranch->id]))
            ->assertOk()
            ->assertViewHas('expected', 600000.0);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['branch_id' => $this->branch->id]))
            ->assertOk()
            ->assertViewHas('expected', 200000.0);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 800000.0);
    }

    public function test_collection_rate_is_computed_against_given_day_expected(): void
    {
        $loan = $this->loan('EXP-L-9');
        $this->installment($loan, 1, today()->subDays(7)->toDateString(), 100000, ['status' => 'overdue']);
        $this->installment($loan, 2, today()->toDateString(), 50000);

        $loan->payments()->create([
            'payment_number' => 'EXP-PAY-1',
            'member_id' => $this->member->id,
            'branch_id' => $this->branch->id,
            'amount' => 60000,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'status' => 'posted',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 50000.0)
            ->assertViewHas('collected', 60000.0)
            ->assertViewHas('collectionRate', 120.0);
    }

    public function test_expected_collection_is_zero_when_there_is_nothing_outstanding(): void
    {
        $loan = $this->loan('EXP-L-10');
        $this->installment($loan, 1, today()->addDays(10)->toDateString(), 900000);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expected', 0.0)
            ->assertViewHas('collectionRate', 0);
    }
}
