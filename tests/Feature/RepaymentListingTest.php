<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RepaymentListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Branch $otherBranch;

    private MemberGroup $group;

    private LoanProduct $product;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-10 08:00:00');

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['branch_id' => null]);
        $this->admin->assignRole('super_admin');

        $region = Region::create(['name' => 'Region', 'code' => 'RPY-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'RPY-A']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'RPY-B', 'branch_name' => 'Repayments Branch']);
        $this->otherBranch = Branch::create(['area_id' => $area->id, 'branch_code' => 'RPY-B2', 'branch_name' => 'Second Repayments Branch']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'RPY-G', 'group_name' => 'Repayments Group']);
        $this->product = LoanProduct::create([
            'name' => 'Repayments Product',
            'code' => 'RPY-P',
            'interest_method' => 'flat',
            'minimum_amount' => 1000,
            'maximum_amount' => 5000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 24,
            'annual_interest_rate' => 10,
        ]);
        $this->member = Member::create([
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'membership_number' => 'RPY-M001',
            'first_name' => 'Rehema',
            'last_name' => 'Mteja',
            'phone' => '255740000001',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_lists_repayments_with_daily_weekly_pending_and_total_stats(): void
    {
        $loan = $this->loan('RPY-L1');

        $this->payment('RPY-P1', $loan, 50000, '2026-06-10 09:00:00');
        $this->payment('RPY-P2', $loan, 25000, '2026-06-08 09:00:00');
        $this->payment('RPY-P3', $loan, 10000, '2026-05-30 09:00:00');
        $this->payment('RPY-P4', $loan, 7000, '2026-06-09 09:00:00', 'reversed');
        $this->installment($loan, '2026-06-05', 40000);
        $this->installment($loan, '2026-08-05', 60000);

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.index'))
            ->assertOk()
            ->assertViewIs('admin.repayments.index')
            ->assertViewHas('activeStatus', '')
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 4)
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame(1, $stats['today']);
                $this->assertSame(50000.0, $stats['todayAmount']);
                $this->assertSame(2, $stats['thisWeek']);
                $this->assertSame(75000.0, $stats['thisWeekAmount']);
                $this->assertSame(1, $stats['pending']);
                $this->assertSame(40000.0, $stats['pendingAmount']);
                $this->assertSame(3, $stats['total']);
                $this->assertSame(85000.0, $stats['totalAmount']);

                return true;
            })
            ->assertSee(__("Today's repayments"))
            ->assertSee(__('Pending'))
            ->assertSee('RPY-P1');
    }

    public function test_it_filters_by_status_method_and_date_range(): void
    {
        $loan = $this->loan('RPY-L1');

        $this->payment('RPY-P1', $loan, 50000, '2026-06-10 09:00:00');
        $this->payment('RPY-P2', $loan, 25000, '2026-06-08 09:00:00', 'posted', 'mpesa');
        $this->payment('RPY-P3', $loan, 10000, '2026-05-30 09:00:00', 'reversed');

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.index', ['status' => 'reversed']))
            ->assertOk()
            ->assertViewHas('activeStatus', 'reversed')
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 1 && $paginator->first()->payment_number === 'RPY-P3')
            ->assertViewHas('statusTabs', function (array $tabs) {
                $counts = array_column($tabs, 'count', 'key');

                $this->assertSame(3, $counts['']);
                $this->assertSame(2, $counts['posted']);
                $this->assertSame(1, $counts['reversed']);

                return true;
            });

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.index', ['payment_method' => 'mpesa']))
            ->assertOk()
            ->assertViewHas('activeMethod', 'mpesa')
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 1);

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 2)
            ->assertViewHas('stats.todayAmount', 50000.0)
            ->assertViewHas('stats.totalAmount', 75000.0);

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.index', ['search' => 'RPY-P2']))
            ->assertOk()
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_an_unknown_status_falls_back_to_all_repayments(): void
    {
        $this->payment('RPY-P1', $this->loan('RPY-L1'), 50000, '2026-06-10 09:00:00');

        $this->actingAs($this->admin)
            ->get(route('admin.repayments.index', ['status' => 'not-a-status']))
            ->assertOk()
            ->assertViewHas('activeStatus', '')
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_the_listing_is_scoped_to_the_branch_of_the_user(): void
    {
        $this->payment('RPY-P1', $this->loan('RPY-L1'), 50000, '2026-06-10 09:00:00');
        $this->payment('RPY-P2', $this->loan('RPY-L2', $this->otherBranch), 40000, '2026-06-10 09:00:00');

        $manager = User::factory()->create(['branch_id' => $this->branch->id]);
        $manager->assignRole('branch_manager');

        $this->actingAs($manager)
            ->get(route('admin.repayments.index'))
            ->assertOk()
            ->assertViewHas('repayments', fn ($paginator) => $paginator->total() === 1)
            ->assertViewHas('stats.todayAmount', 50000.0);
    }

    public function test_the_sidebar_exposes_repayments_below_loans_and_collections(): void
    {
        $content = $this->actingAs($this->admin)->get(route('admin.loans.index'))->assertOk()->getContent();

        preg_match('/<nav>(.*?)<\/nav>/s', $content, $matches);
        $sidebar = $matches[1] ?? '';

        $this->assertNotEmpty($sidebar, 'The sidebar navigation was not rendered.');
        $this->assertMatchesRegularExpression('/<span class="ph ph-hand-coins nav-icon"/', $sidebar);

        $loans = strpos($sidebar, e(route('admin.loans.index')));
        $repayments = strpos($sidebar, e(route('admin.repayments.index')));

        $this->assertNotFalse($repayments, 'The sidebar is missing the repayments link.');
        $this->assertLessThan($repayments, $loans, 'Repayments should be listed below Loans & Collections.');
    }

    public function test_users_without_the_view_payments_permission_are_denied(): void
    {
        $staff = User::factory()->create(['branch_id' => $this->branch->id]);
        $staff->assignRole('member');

        $this->actingAs($staff)
            ->get(route('admin.repayments.index'))
            ->assertForbidden();
    }

    public function test_the_receipt_row_actions_use_icons_only(): void
    {
        $this->payment('RPY-P1', $this->loan('RPY-L1'), 50000, '2026-06-10 09:00:00');

        $content = $this->actingAs($this->admin)->get(route('admin.repayments.index'))->assertOk()->getContent();

        preg_match('/<div class="table-actions">.*?<\/div>/s', $content, $matches);

        $this->assertNotEmpty($matches, 'No table actions markup found.');
        $this->assertSame('', trim(preg_replace('/\s+/', ' ', strip_tags($matches[0]))));
        $this->assertMatchesRegularExpression('/aria-label="View"/', $matches[0]);
    }

    public function test_it_exports_the_filtered_list(): void
    {
        $this->payment('RPY-P1', $this->loan('RPY-L1'), 50000, '2026-06-10 09:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.repayments.export.list', ['format' => 'pdf']))->assertOk();

        $this->assertStringContainsString('VATI-repayments-'.now()->format('Ymd-His'), (string) $response->headers->get('content-disposition'));
    }

    private function loan(string $number, ?Branch $branch = null): Loan
    {
        $loanBranch = $branch ?? $this->branch;

        $application = LoanApplication::create([
            'application_number' => 'RPY-APP-'.$number,
            'member_id' => $this->member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $this->group->id,
            'branch_id' => $loanBranch->id,
            'requested_amount' => 100000,
            'duration_months' => 6,
            'status' => ApplicationStatus::DISBURSED,
            'created_by' => $this->admin->id,
        ]);

        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $this->member->id,
            'group_id' => $this->group->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => $loanBranch->id,
            'principal_amount' => 100000,
            'interest_amount' => 10000,
            'total_repayment' => 110000,
            'principal_balance' => 100000,
            'interest_balance' => 10000,
            'total_balance' => 110000,
            'number_of_installments' => 6,
            'installment_amount' => 18333,
            'status' => LoanStatus::ACTIVE,
        ]);
    }

    private function payment(string $number, Loan $loan, int $amount, string $paidAt, string $status = 'posted', string $method = 'cash'): Payment
    {
        return Payment::create([
            'payment_number' => $number,
            'member_id' => $loan->member_id,
            'loan_id' => $loan->id,
            'branch_id' => $loan->branch_id,
            'amount' => $amount,
            'payment_method' => $method,
            'paid_at' => $paidAt,
            'collected_by' => $this->admin->id,
            'status' => $status,
        ]);
    }

    private function installment(Loan $loan, string $dueDate, int $outstanding): LoanInstallment
    {
        return LoanInstallment::create([
            'loan_id' => $loan->id,
            'installment_number' => LoanInstallment::where('loan_id', $loan->id)->count() + 1,
            'due_date' => $dueDate,
            'principal_due' => $outstanding,
            'interest_due' => 0,
            'total_due' => $outstanding,
            'outstanding_balance' => $outstanding,
            'status' => $dueDate < now()->toDateString() ? 'overdue' : 'upcoming',
        ]);
    }
}
