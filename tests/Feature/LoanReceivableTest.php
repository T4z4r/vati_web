<?php

namespace Tests\Feature;

use App\Models\{Area, Branch, LoanApplication, LoanProduct, Member, MemberGroup, Region, User};
use App\Services\{LoanApprovalService, LoanCalculatorService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanReceivableTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_and_disbursement_use_net_amount_and_saved_charges(): void
    {
        $region = Region::create(['name' => 'Region']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'FEE', 'branch_name' => 'Fees']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'FEE-G', 'group_name' => 'Fees']);
        $user = User::factory()->create();
        $member = Member::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'FEE-M', 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255711111112']);
        $product = LoanProduct::create(['name' => 'Fees Loan', 'code' => 'FEE', 'minimum_amount' => 1000, 'maximum_amount' => 2000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'repayment_frequency' => 'weekly', 'security_percentage' => 10]);
        $application = LoanApplication::create(['application_number' => 'FEE-A', 'member_id' => $member->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'loan_product_id' => $product->id, 'requested_amount' => 1000000, 'duration_months' => 6, 'status' => 'submitted', 'created_by' => $user->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/loan-applications')->assertOk()
            ->assertJsonPath('data.0.amount_receivable', '695000.00')
            ->assertJsonPath('data.0.calculator_breakdown.amount_receivable', '695000.00')
            ->assertJsonPath('data.0.calculator_breakdown.vat', '180000.00')
            ->assertJsonStructure(['data', 'links', 'meta']);
        $application->update(['calc_total_repayment' => 1000000, 'calc_security_amount' => 100000, 'calc_charges' => 205000]);
        $this->getJson('/api/v1/loan-applications')->assertOk()->assertJsonPath('data.0.amount_receivable', '695000.00');
        $application->update(['recommended_amount' => 1100000]);
        try {
            app(LoanApprovalService::class)->decide($application, $user, 'approved');
            $this->fail('Approval above the requested amount must be rejected.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cannot exceed the requested amount', $e->getMessage());
        }
        $this->assertDatabaseCount('loans', 0);
        $this->assertSame('submitted', $application->fresh()->status->value);
        $application->update(['recommended_amount' => null]);
        $loan = app(LoanApprovalService::class)->decide($application, $user, 'approved')->loan;
        $this->assertSame('695000.00', $loan->amount_receivable);
        $this->assertSame('205000.00', $loan->calc_charges);
        $this->assertSame('180000.00', $loan->calc_vat);
        $this->assertSame('18.0000', $product->fresh()->vat_percentage);
        $product->update(['processing_fee_percentage' => 50]);
        // An old cached receivable must not allow over-disbursement.
        $loan->update(['calc_amount_receivable' => 1000000]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/loans')->assertOk()->assertJsonPath('data.0.amount_receivable', '695000.00')->assertJsonPath('data.0.charges', '205000.00');
        $url = '/api/v1/loans/'.$loan->id.'/disburse';
        $this->postJson($url, ['method' => 'cash', 'amount' => 695000])->assertConflict();
        foreach ([null, 0] as $invalidSavedAmount) {
            $loan->update(['calc_amount_receivable' => $invalidSavedAmount]);
            $this->postJson($url, ['method' => 'cash', 'amount' => 695000])->assertConflict();
        }
        $loan->update(['calc_amount_receivable' => 695000]);
        foreach ([null, 0, -1, 'invalid', '695000.001'] as $invalidAmount) {
            $this->postJson($url, ['method' => 'cash', 'amount' => $invalidAmount])->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->postJson($url, ['method' => 'cash'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson('/api/v1/loans/'.$loan->id.'/disburse', ['method' => 'cash', 'amount' => 1000000])
            ->assertConflict();
        $this->assertDatabaseCount('loan_disbursements', 0);
        $this->assertDatabaseCount('security_transactions', 0);
        // A failure after crediting security must roll back issuance and the credit.
        \App\Models\SecurityTransaction::created(fn () => throw new \RuntimeException('Simulated ledger failure'));
        try {
            $this->postJson($url, ['method' => 'cash', 'amount' => 695000])->assertServerError();
            $this->assertDatabaseCount('loan_disbursements', 0);
            $this->assertDatabaseCount('security_transactions', 0);
            $this->assertDatabaseCount('member_security_accounts', 0);
            $this->assertSame('pending_disbursement', $loan->fresh()->status->value);
        } finally {
            \App\Models\SecurityTransaction::flushEventListeners();
        }
        // Preserve the member's existing savings when adding the loan security.
        app(\App\Services\SecurityAccountService::class)->transact($member, $user, 'deposit', 25000);
        $this->postJson($url, ['method' => 'cash', 'amount' => '695000.00'])
            ->assertCreated()->assertJsonPath('data.id', $loan->id)->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.issued_amount', '695000.00')->assertJsonPath('data.disbursement.amount', '695000.00');
        $this->postJson($url, ['method' => 'cash', 'amount' => 695000])->assertConflict();
        $this->assertDatabaseCount('loan_disbursements', 1);
        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $member->id, 'balance' => 125000]);
        $this->assertDatabaseHas('security_transactions', [
            'loan_id' => $loan->id, 'transaction_type' => 'deposit', 'amount' => 100000,
            'balance_before' => 25000, 'balance_after' => 125000, 'created_by' => $user->id,
        ]);
        $this->assertSame(1, \App\Models\SecurityTransaction::where('loan_id', $loan->id)->count());
        $this->getJson('/api/v1/members/'.$member->id.'/security')->assertOk()->assertJsonPath('data.balance', 125000);
        $this->assertDatabaseHas('loan_disbursements', ['loan_id' => $loan->id, 'amount' => 695000]);
        $this->assertSame('1000000.00', $loan->fresh()->principal_amount);
        $this->assertSame('695000.00', $loan->fresh()->calc_amount_receivable);
        $this->getJson('/api/v1/portfolio/summary')->assertOk()->assertJsonPath('data.total_issued_amount', '695000.00');
        $this->assertSame('1000000.00', $loan->fresh()->total_balance);
        $this->assertSame(1000000.0, round((float) $loan->installments()->sum('total_due'), 2));
        $this->assertSame(26, $loan->installments()->count());
        $payment = app(\App\Services\PaymentService::class)->post($loan, $user, 100000, ['payment_method' => 'cash']);
        $this->assertSame('900000.00', $loan->fresh()->total_balance);
        app(\App\Services\PaymentService::class)->reverse($payment, $user, 'Test reversal');
        $this->assertSame('1000000.00', $loan->fresh()->total_balance);
        app(\App\Services\PaymentService::class)->post($loan, $user, 1000000, ['payment_method' => 'cash']);
        $this->assertSame('0.00', $loan->fresh()->total_balance);
        $this->assertSame('settled', $loan->fresh()->status->value);
    }

    public function test_calculator_rejects_deductions_exceeding_principal(): void
    {
        $product = new LoanProduct(['minimum_amount' => 1, 'maximum_amount' => 10000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'repayment_frequency' => 'monthly', 'processing_fee_percentage' => 80, 'security_percentage' => 30]);
        $this->expectException(\DomainException::class);
        app(LoanCalculatorService::class)->calculate($product, 1000, 6);
    }

    public function test_only_four_principal_deductions_apply_even_with_legacy_extra_fees(): void
    {
        $product = new LoanProduct([
            'minimum_amount' => 1, 'maximum_amount' => 2000000,
            'minimum_duration_months' => 1, 'maximum_duration_months' => 12,
            'repayment_frequency' => 'monthly', 'annual_interest_rate' => 24,
            'transaction_fee_percentage' => 5, 'membership_fee' => 5000,
        ]);

        $figures = app(LoanCalculatorService::class)->calculate($product, 1000000, 6);

        $this->assertSame(10000.0, $figures['processing_fee']);
        $this->assertSame(15000.0, $figures['insurance_fee']);
        $this->assertSame(180000.0, $figures['vat']);
        $this->assertSame(100000.0, $figures['security_amount']);
        $this->assertSame(205000.0, $figures['charges']);
        $this->assertSame(695000.0, $figures['amount_receivable']);
        $this->assertSame(1000000.0, $figures['total_repayment']);
        $this->assertSame(0.0, $figures['interest']);
    }

    public function test_every_duration_keeps_repayment_equal_to_principal(): void
    {
        foreach (['weekly', 'monthly'] as $frequency) {
            $product = new LoanProduct(['minimum_amount' => 1, 'maximum_amount' => 2000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'repayment_frequency' => $frequency]);
            foreach (range(1, 12) as $months) {
                $figures = app(LoanCalculatorService::class)->calculate($product, 1000000.01, $months);
                $this->assertSame(1000000.01, $figures['total_repayment']);
                $this->assertSame(0.0, $figures['interest']);
                $this->assertLessThanOrEqual($figures['principal'], round($figures['installment_amount'] * $figures['installment_count'], 2));
            }
        }
    }
}
