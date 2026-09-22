<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use App\Models\Region;
use App\Models\User;
use App\Services\VatCorrectionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatCorrectionTest extends TestCase
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
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'VAT-B', 'branch_name' => 'VAT']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'VAT-G', 'group_name' => 'VAT']);
        $user = User::factory()->create();
        $member = Member::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'VAT-M', 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255711111113']);
        $product = $this->product();

        return compact('branch', 'group', 'member', 'product', 'user');
    }

    private function outdatedApplication(array $fixtures, string $number = 'VAT-A-1'): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => $number,
            'member_id' => $fixtures['member']->id,
            'group_id' => $fixtures['group']->id,
            'branch_id' => $fixtures['branch']->id,
            'loan_product_id' => $fixtures['product']->id,
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'status' => 'submitted',
            'created_by' => $fixtures['user']->id,
            'calc_vat' => 180000,
            'calc_insurance_fee' => 15000,
            'calc_processing_fee' => 10000,
            'calc_security_amount' => 100000,
            'calc_charges' => 205000,
            'calc_amount_receivable' => 695000,
            'calc_total_repayment' => 1000000,
            'calc_interest' => 0,
        ]);
    }

    private function outdatedLoan(LoanApplication $application, array $fixtures, string $number = 'VAT-L-1'): Loan
    {
        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $fixtures['member']->id,
            'group_id' => $fixtures['group']->id,
            'loan_product_id' => $fixtures['product']->id,
            'branch_id' => $fixtures['branch']->id,
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
            'processing_fee' => 10000,
            'total_fees_and_vat' => 205000,
            'calc_insurance_fee' => 15000,
            'calc_vat' => 180000,
            'calc_security_amount' => 100000,
            'calc_charges' => 205000,
            'calc_amount_receivable' => 695000,
        ]);
    }

    private function fillInstallments(Loan $loan, float $totalDue = 200000): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $loan->installments()->create([
                'installment_number' => $i,
                'due_date' => now()->addWeeks($i)->toDateString(),
                'total_due' => $totalDue,
            ]);
        }
    }

    public function test_estimate_counts_outdated_figures(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures);
        $loan = $this->outdatedLoan($application, $fixtures, 'VAT-L-1');
        $this->fillInstallments($loan);

        $estimate = app(VatCorrectionService::class)->estimate($fixtures['branch']->id);

        $this->assertSame(1, $estimate['applications']);
        $this->assertSame(1, $estimate['loans']);
        $this->assertSame(1, $estimate['repayment']);
        $this->assertSame(1, $estimate['schedule_eligible']);
        $this->assertSame(1, $estimate['schedule']);
        $this->assertSame(0, $estimate['loans_with_payments_skipped_for_schedule']);
        $this->assertSame(0, $estimate['applications_skipped']);
    }

    public function test_correct_updates_figures_and_regenerates_schedule(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures);
        $loan = $this->outdatedLoan($application, $fixtures, 'VAT-L-1');
        $this->fillInstallments($loan);

        $result = app(VatCorrectionService::class)->correct($fixtures['branch']->id);

        $this->assertSame(1, $result['applications']);
        $this->assertSame(1, $result['loans']);
        $this->assertSame(1, $result['repayment']);
        $this->assertSame(1, $result['schedule']);

        $application->refresh();
        $this->assertAmount(1800, $application->calc_vat, 'application calc_vat');
        $this->assertAmount(26800, $application->calc_charges, 'application calc_charges');
        $this->assertAmount(100000, $application->calc_security_amount, 'application calc_security_amount');
        $this->assertAmount(873200, $application->calc_amount_receivable, 'application calc_amount_receivable');
        $this->assertAmount(1126009.26, $application->calc_total_repayment, 'application calc_total_repayment');
        $this->assertAmount(126009.26, $application->calc_interest, 'application calc_interest');
        $this->assertAmount(15000, $application->calc_insurance_fee, 'application calc_insurance_fee');
        $this->assertAmount(10000, $application->calc_processing_fee, 'application calc_processing_fee');

        $loan->refresh();
        $this->assertAmount(10000, $loan->processing_fee, 'loan processing_fee');
        $this->assertAmount(15000, $loan->calc_insurance_fee, 'loan calc_insurance_fee');
        $this->assertAmount(1800, $loan->calc_vat, 'loan calc_vat');
        $this->assertAmount(26800, $loan->calc_charges, 'loan calc_charges');
        $this->assertAmount(100000, $loan->calc_security_amount, 'loan calc_security_amount');
        $this->assertAmount(873200, $loan->calc_amount_receivable, 'loan calc_amount_receivable');
        $this->assertAmount(26800, $loan->total_fees_and_vat, 'loan total_fees_and_vat');
        $this->assertAmount(166666.66, $loan->installment_amount, 'loan installment_amount');
        $this->assertAmount(166666.67, $loan->weekly_installment, 'loan weekly_installment');

        $this->assertSame(6, $loan->installments()->count());
        $this->assertSame(1000000.0, round((float) $loan->installments()->sum('total_due'), 2));
        $totals = $loan->installments()->orderBy('installment_number')->pluck('total_due')
            ->map(fn ($total) => round((float) $total, 2))->all();
        $this->assertSame([166666.67, 166666.67, 166666.67, 166666.67, 166666.67, 166666.65], $totals);
    }

    public function test_correct_keeps_schedule_for_loans_with_posted_payments(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures, 'VAT-A-2');
        $loan = $this->outdatedLoan($application, $fixtures, 'VAT-L-2');
        $this->fillInstallments($loan);

        Payment::create([
            'payment_number' => 'VAT-PAY-1',
            'member_id' => $fixtures['member']->id,
            'loan_id' => $loan->id,
            'branch_id' => $fixtures['branch']->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $result = app(VatCorrectionService::class)->correct($fixtures['branch']->id);

        $this->assertSame(0, $result['schedule'], 'Schedule must not be regenerated for a loan with a posted payment.');
        $this->assertSame(1, $result['loans']);

        $this->assertSame(6, $loan->installments()->count());
        $this->assertSame(1200000.0, round((float) $loan->installments()->sum('total_due'), 2));

        $loan->refresh();
        $this->assertAmount(1800, $loan->calc_vat, 'loan calc_vat');
        $this->assertAmount(166666.66, $loan->installment_amount, 'loan installment_amount');
        $this->assertAmount(166666.67, $loan->weekly_installment, 'loan weekly_installment');
    }

    public function test_correct_reports_nothing_to_correct_when_figures_are_current(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures, 'VAT-A-3');
        $loan = $this->outdatedLoan($application, $fixtures, 'VAT-L-3');
        $this->fillInstallments($loan);

        $service = app(VatCorrectionService::class);
        $service->correct($fixtures['branch']->id);

        $result = $service->correct($fixtures['branch']->id);

        $this->assertSame(0, $result['applications']);
        $this->assertSame(0, $result['loans']);
        $this->assertSame(0, $result['repayment']);
        $this->assertSame(0, $result['schedule']);
        $this->assertStringContainsString('No outdated', $result['message']);
    }

    public function test_estimate_respects_branch_filter(): void
    {
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures);
        $loan = $this->outdatedLoan($application, $fixtures, 'VAT-L-4');
        $this->fillInstallments($loan);

        $region = Region::create(['name' => 'Other', 'code' => 'O']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Other', 'code' => 'OA']);
        $otherBranch = Branch::create(['area_id' => $area->id, 'branch_code' => 'OTH', 'branch_name' => 'Other']);

        $estimate = app(VatCorrectionService::class)->estimate($otherBranch->id);

        $this->assertSame(0, $estimate['applications']);
        $this->assertSame(0, $estimate['loans']);
    }

    public function test_admin_can_preview_and_execute_correction_over_http(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures);
        $loan = $this->outdatedLoan($application, $fixtures, 'VAT-L-5');
        $this->fillInstallments($loan);

        $admin = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $admin->assignRole('super_admin');

        $plain = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $this->actingAs($plain)
            ->get(route('admin.system.data.vat-correct.preview'))
            ->assertForbidden();
        $this->actingAs($plain)
            ->post(route('admin.system.data.vat-correct'), ['expected_phrase' => 'CORRECT VAT', 'confirmation_phrase' => 'CORRECT VAT'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.system.data.vat-correct.preview'))
            ->assertOk()
            ->assertJsonPath('data.applications', 1)
            ->assertJsonPath('data.loans', 1)
            ->assertJsonPath('data.schedule', 1);

        $this->actingAs($admin)
            ->post(route('admin.system.data.vat-correct'), [
                'expected_phrase' => 'CORRECT VAT',
                'confirmation_phrase' => 'CORRECT VAT',
                'branch_id' => $fixtures['branch']->id,
            ])
            ->assertRedirect(route('admin.system.data'));

        $this->assertAmount(1800, $application->refresh()->calc_vat, 'application calc_vat');
        $this->assertAmount(1800, $loan->refresh()->calc_vat, 'loan calc_vat');
        $this->assertDatabaseHas('activity_log', ['description' => 'VAT computation corrected for existing loans and applications']);
    }

    public function test_correct_requires_confirmation_phrase(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->outdatedApplication($fixtures);
        $this->outdatedLoan($application, $fixtures, 'VAT-L-6');

        $admin = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.system.data.vat-correct'), [
                'expected_phrase' => 'CORRECT VAT',
                'confirmation_phrase' => 'nope',
            ])
            ->assertSessionHasErrors('confirmation_phrase');
    }
}