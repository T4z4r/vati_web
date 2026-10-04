<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\MissingLoanService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissingLoanCheckTest extends TestCase
{
    use RefreshDatabase;

    private function assertAmount(float|string $expected, mixed $actual, string $field = ''): void
    {
        $this->assertSame(
            round((float) $expected, 2),
            round((float) $actual, 2),
            "Mismatch for {$field}."
        );
    }

    private function product(): LoanProduct
    {
        return LoanProduct::create([
            'name' => 'Standards Loan',
            'code' => 'STD',
            'minimum_amount' => 1000,
            'maximum_amount' => 2000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 12,
            'repayment_frequency' => 'monthly',
        ]);
    }

    private function fixtures(): array
    {
        $region = Region::create(['name' => 'Region', 'code' => 'R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'ML-B', 'branch_name' => 'ML']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'ML-G', 'group_name' => 'ML Group']);
        $user = User::factory()->create();
        $member = Member::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'ML-M', 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255711111119']);

        return compact('branch', 'group', 'member', 'user') + ['product' => $this->product()];
    }

    private function application(array $fixtures, string $number, string $status = 'approved', array $overrides = []): LoanApplication
    {
        return LoanApplication::create(array_merge([
            'application_number' => $number,
            'member_id' => $fixtures['member']->id,
            'group_id' => $fixtures['group']->id,
            'branch_id' => $fixtures['branch']->id,
            'loan_product_id' => $fixtures['product']->id,
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'status' => $status,
            'created_by' => $fixtures['user']->id,
        ], $overrides));
    }

    private function issue(LoanApplication $application): Loan
    {
        return Loan::create([
            'loan_number' => $application->application_number.'-L',
            'loan_application_id' => $application->id,
            'member_id' => $application->member_id,
            'group_id' => $application->group_id,
            'loan_product_id' => $application->loan_product_id,
            'branch_id' => $application->branch_id,
            'principal_amount' => 1000000,
            'interest_amount' => 0,
            'total_repayment' => 1000000,
            'principal_balance' => 1000000,
            'interest_balance' => 0,
            'total_balance' => 1000000,
            'number_of_installments' => 6,
            'installment_amount' => 20000,
            'weekly_installment' => 20000,
            'first_payment_date' => now()->addWeek(),
            'status' => 'active',
        ]);
    }

    public function test_report_flags_only_approved_applications_without_a_loan(): void
    {
        $fixtures = $this->fixtures();
        $this->application($fixtures, 'ML-A-1', 'approved');
        $this->application($fixtures, 'ML-A-2', 'disbursement_pending');
        $this->application($fixtures, 'ML-A-3', 'submitted');
        $this->application($fixtures, 'ML-A-4', 'rejected');
        $this->application($fixtures, 'ML-A-5', 'recommended');
        $linked = $this->application($fixtures, 'ML-A-6', 'disbursed');
        $this->issue($linked);

        $report = app(MissingLoanService::class)->run($fixtures['branch']->id);

        $this->assertSame(2, $report['missing']);
        $this->assertSame(0, $report['created']);
        $this->assertSame(0, $report['failed']);
        $this->assertSame(['ML-A-2', 'ML-A-1'], array_column($report['applications'], 'application_number'));
        $this->assertSame('Asha Musa', $report['applications'][1]['member']);
        $this->assertSame('ML Group', $report['applications'][1]['group']);
        $this->assertSame('Standards Loan', $report['applications'][1]['product']);
        $this->assertSame(1000000.0, $report['applications'][1]['amount']);
        $this->assertSame(6, $report['applications'][1]['duration']);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_report_respects_branch_scope(): void
    {
        $fixtures = $this->fixtures();
        $this->application($fixtures, 'ML-A-7');

        $region = Region::create(['name' => 'Other', 'code' => 'O']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Other', 'code' => 'OA']);
        $otherBranch = Branch::create(['area_id' => $area->id, 'branch_code' => 'OTH', 'branch_name' => 'Other']);

        $this->assertSame(0, app(MissingLoanService::class)->run($otherBranch->id)['missing']);
        $this->assertSame(1, app(MissingLoanService::class)->run()['missing']);
    }

    public function test_creating_missing_loans_opens_priced_accounts(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures, 'ML-A-8', 'approved', [
            'recommended_amount' => 900000,
            'recommended_duration_months' => 6,
        ]);
        $this->application($fixtures, 'ML-A-9', 'disbursed');

        $report = app(MissingLoanService::class)->run($fixtures['branch']->id, true);

        $this->assertSame(2, $report['missing']);
        $this->assertSame(2, $report['created']);
        $this->assertSame(0, $report['failed']);
        $this->assertStringContainsString('Opened 2 of 2', $report['message']);
        $this->assertDatabaseCount('loans', 2);

        $loan = Loan::where('loan_application_id', $application->id)->firstOrFail();
        $this->assertSame($application->member_id, $loan->member_id);
        $this->assertSame($application->group_id, $loan->group_id);
        $this->assertSame($application->branch_id, $loan->branch_id);
        $this->assertSame($fixtures['product']->id, $loan->loan_product_id);
        $this->assertStringStartsWith('VATI-LN-', $loan->loan_number);
        $this->assertAmount(900000, $loan->principal_amount, 'loan principal_amount');
        $this->assertAmount(900000, $loan->principal_balance, 'loan principal_balance');
        $this->assertSame(6, $loan->number_of_installments);
        // Flat factor 0.0445 weekly scaled to monthly (x4) on the full principal.
        $this->assertAmount(0.0445, $loan->interest_rate, 'loan interest_rate');
        $this->assertAmount(961200.0, $loan->total_repayment, 'loan total_repayment');
        $this->assertAmount(961200.0, $loan->total_balance, 'loan total_balance');
        $this->assertAmount(61200.0, $loan->interest_balance, 'loan interest_balance');
        $this->assertAmount(61200.0, $loan->interest_amount, 'loan interest_amount');
        $this->assertAmount(160200.0, $loan->installment_amount, 'loan installment_amount');
        $this->assertNotNull($loan->calc_amount_receivable);
    }

    public function test_creating_missing_loans_is_idempotent(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures, 'ML-A-10');
        $this->issue($application);

        $report = app(MissingLoanService::class)->run($fixtures['branch']->id, true);

        $this->assertSame(0, $report['missing']);
        $this->assertSame(0, $report['created']);
        $this->assertStringContainsString('no gaps found', $report['message']);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_unpriceable_application_is_reported_without_blocking_the_batch(): void
    {
        $fixtures = $this->fixtures();
        $this->application($fixtures, 'ML-A-11', 'approved', ['requested_amount' => 5000000]);
        $this->application($fixtures, 'ML-A-12');

        $report = app(MissingLoanService::class)->run($fixtures['branch']->id, true);

        $this->assertSame(2, $report['missing']);
        $this->assertSame(1, $report['created']);
        $this->assertSame(1, $report['failed']);
        $this->assertSame('ML-A-11', $report['failures'][0]['application_number']);
        $this->assertStringContainsString('outside the product limits', $report['failures'][0]['reason']);
        $this->assertStringContainsString('1 could not be priced', $report['message']);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_super_admin_can_check_and_create_from_the_list_page(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures, 'ML-A-LIST');

        $admin = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('admin.loan-applications.index'))
            ->assertOk()
            ->assertSee(route('admin.loan-applications.check-missing-loans'), false)
            ->assertSee('Check missing loans')
            ->assertDontSee('Create missing loans');

        $this->actingAs($admin)
            ->post(route('admin.loan-applications.check-missing-loans'))
            ->assertOk()
            ->assertSee('Applications missing a loan account')
            ->assertSee('ML-A-LIST')
            ->assertSee('Create missing loans (1)')
            ->assertSee(route('admin.loan-applications.create-missing-loans'), false);

        $this->assertDatabaseCount('loans', 0);

        $this->actingAs($admin)
            ->post(route('admin.loan-applications.create-missing-loans'))
            ->assertOk()
            ->assertSee('Opened 1 of 1 missing loan account(s).')
            ->assertSee('Loan accounts opened');

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('loans', ['loan_application_id' => $application->id]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'Loan accounts opened for loan applications missing one',
        ]);
    }

    public function test_operations_are_restricted_to_super_admin_and_head_office(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $this->application($fixtures, 'ML-A-13');

        $plain = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $plain->assignRole('auditor');

        $this->actingAs($plain)
            ->get(route('admin.loan-applications.index'))
            ->assertOk()
            ->assertDontSee(route('admin.loan-applications.check-missing-loans'), false);

        $this->actingAs($plain)
            ->post(route('admin.loan-applications.check-missing-loans'))
            ->assertForbidden();

        $this->actingAs($plain)
            ->post(route('admin.loan-applications.create-missing-loans'))
            ->assertForbidden();

        $this->assertDatabaseCount('loans', 0);

        $headOffice = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $headOffice->assignRole('head_office_admin');

        $this->actingAs($headOffice)
            ->post(route('admin.loan-applications.check-missing-loans'))
            ->assertOk()
            ->assertSee('ML-A-13');
    }
}
