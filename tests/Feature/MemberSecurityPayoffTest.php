<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberSecurityAccount;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberSecurityPayoffTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Member $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Kinondoni Group']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');
        $this->member = Member::create([
            'branch_id' => $this->branch->id, 'group_id' => $group->id, 'membership_number' => 'DSM-M-01',
            'first_name' => 'Asha', 'last_name' => 'Juma', 'phone' => '255710000001', 'created_by' => $this->admin->id,
        ]);
        Sanctum::actingAs($this->admin);
    }

    private function loan(string $number, float $principalBalance, float $interestBalance, string $status = 'active', string $maturity = '2026-01-31'): Loan
    {
        $product = LoanProduct::create(['name' => 'Loan '.$number, 'code' => 'LP-'.$number, 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 24, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
        $application = LoanApplication::create(['application_number' => 'APP-'.$number, 'member_id' => $this->member->id, 'loan_product_id' => $product->id, 'group_id' => $this->member->group_id, 'branch_id' => $this->branch->id, 'requested_amount' => $principalBalance + $interestBalance, 'duration_months' => 6, 'status' => 'disbursed', 'created_by' => $this->admin->id]);

        return Loan::create([
            'loan_number' => 'L-'.$number, 'loan_application_id' => $application->id, 'member_id' => $this->member->id,
            'group_id' => $this->member->group_id, 'loan_product_id' => $product->id, 'branch_id' => $this->branch->id,
            'principal_amount' => $principalBalance, 'interest_amount' => $interestBalance, 'total_repayment' => $principalBalance + $interestBalance,
            'principal_balance' => $principalBalance, 'interest_balance' => $interestBalance, 'total_balance' => $principalBalance + $interestBalance,
            'number_of_installments' => 6, 'installment_amount' => 1000, 'maturity_date' => $maturity, 'status' => $status,
        ]);
    }

    private static int $securitySequence = 0;

    private function securityBalance(float $balance): MemberSecurityAccount
    {
        $account = MemberSecurityAccount::updateOrCreate(['member_id' => $this->member->id], ['balance' => $balance]);
        $account->transactions()->create([
            'transaction_number' => 'VATI-SEC-'.$this->member->id.'-'.(++self::$securitySequence), 'transaction_type' => 'deposit', 'amount' => $balance,
            'balance_before' => 0, 'balance_after' => $balance, 'created_by' => $this->admin->id, 'transaction_date' => now(),
        ]);

        return $account;
    }

    private function payOff(array $payload = []): TestResponse
    {
        return $this->postJson("/api/v1/members/{$this->member->id}/security-payoff", $payload);
    }

    public function test_payoff_settles_eligible_loans_before_refunding_the_remainder(): void
    {
        $this->securityBalance(500000);
        $loan = $this->loan('1', 250000, 50000);

        $this->payOff(['payout_method' => 'mpesa', 'payout_reference' => 'MP123'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.offset_total', 300000)
            ->assertJsonPath('data.refunded', 200000)
            ->assertJsonPath('data.balance_after', 0)
            ->assertJsonPath('data.refund.payout_method', 'mpesa')
            ->assertJsonPath('data.refund.payout_reference', 'MP123')
            ->assertJsonPath('data.loans_settled.0.interest_offset', 50000)
            ->assertJsonPath('data.loans_settled.0.principal_offset', 250000);

        $loan->refresh();
        $this->assertSame('settled', $loan->status->value);
        $this->assertEquals(0, (float) $loan->principal_balance);
        $this->assertEquals(0, (float) $loan->interest_balance);
        $this->assertEquals(0, (float) $loan->total_balance);

        $this->assertDatabaseHas('loan_settlements', ['loan_id' => $loan->id, 'security_offset' => 300000, 'cash_payment' => 0, 'interest_waived' => 0, 'final_balance' => 0, 'approved_by' => $this->admin->id]);
        $this->assertDatabaseHas('loan_clearances', ['loan_id' => $loan->id, 'security_offset' => 300000, 'loan_outstanding_amount' => 0, 'status' => 'pending']);
        $this->assertDatabaseHas('security_transactions', ['loan_id' => $loan->id, 'transaction_type' => 'loan_offset', 'amount' => 300000, 'balance_before' => 500000, 'balance_after' => 200000]);
        $this->assertDatabaseHas('security_transactions', ['transaction_type' => 'refund', 'amount' => 200000, 'payout_method' => 'mpesa', 'payout_reference' => 'MP123', 'balance_before' => 200000, 'balance_after' => 0]);
        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 0]);
    }

    public function test_payoff_refunds_the_whole_balance_when_no_loan_is_eligible(): void
    {
        $this->securityBalance(120000);
        $this->loan('1', 250000, 50000, 'settled');

        $this->payOff(['payout_method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.offset_total', 0)
            ->assertJsonPath('data.refunded', 120000)
            ->assertJsonPath('data.loans_settled', [])
            ->assertJsonPath('data.refund.amount', 120000);

        $this->assertDatabaseCount('loan_settlements', 0);
        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 0]);
    }

    public function test_payoff_consumes_security_across_several_loans_oldest_first(): void
    {
        $this->securityBalance(280000);
        $newer = $this->loan('2', 100000, 20000, 'active', '2026-06-30');
        $older = $this->loan('1', 150000, 10000, 'active', '2026-03-31');

        $this->payOff()
            ->assertOk()
            ->assertJsonPath('data.offset_total', 280000)
            ->assertJsonPath('data.refunded', 0)
            ->assertJsonPath('data.refund', null)
            ->assertJsonPath('data.loans_settled.0.loan_id', $older->id)
            ->assertJsonPath('data.loans_settled.0.offset', 160000)
            ->assertJsonPath('data.loans_settled.1.loan_id', $newer->id)
            ->assertJsonPath('data.loans_settled.1.offset', 120000)
            ->assertJsonPath('data.balance_after', 0);

        $this->assertSame('settled', $older->fresh()->status->value);
        $this->assertSame('settled', $newer->fresh()->status->value);
        $this->assertDatabaseCount('loan_settlements', 2);
    }

    public function test_payoff_requires_a_payout_method_when_a_refund_remains(): void
    {
        $this->securityBalance(500000);
        $loan = $this->loan('1', 250000, 50000);

        $this->payOff()
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A payout method is required to refund the remaining security of TZS 200,000.00 to the member.');

        $this->assertSame('active', $loan->fresh()->status->value);
        $this->assertEquals(300000, (float) $loan->fresh()->total_balance);
        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 500000]);
        $this->assertDatabaseCount('loan_settlements', 0);
    }

    public function test_payoff_rejects_an_amount_above_the_available_balance(): void
    {
        $this->securityBalance(100000);

        $this->payOff(['amount' => 150000])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The pay-off amount cannot exceed the available security balance of TZS 100,000.00.');

        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 100000]);
    }

    public function test_payoff_rejects_a_member_without_a_security_balance(): void
    {
        MemberSecurityAccount::create(['member_id' => $this->member->id, 'balance' => 0]);

        $this->payOff()
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This member has no security balance to pay off.');

        $this->assertDatabaseCount('security_transactions', 0);
    }

    public function test_payoff_rejects_a_member_without_a_security_account(): void
    {
        $this->payOff(['amount' => 1000])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This member has no security account to pay off.');
    }

    public function test_payoff_rejects_non_positive_amounts(): void
    {
        $this->securityBalance(100000);

        $this->payOff(['amount' => 0])->assertStatus(422);
        $this->payOff(['amount' => -50])->assertStatus(422);

        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 100000]);
    }

    public function test_payoff_is_allowed_for_roles_with_manage_security_and_forbidden_otherwise(): void
    {
        $officer = User::factory()->create(['branch_id' => $this->branch->id]);
        $officer->assignRole('loan_officer');
        $auditor = User::factory()->create(['branch_id' => $this->branch->id]);
        $auditor->assignRole('auditor');

        $this->securityBalance(50000);
        Sanctum::actingAs($officer);
        $this->payOff(['payout_method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.refunded', 50000);

        $this->securityBalance(50000);
        Sanctum::actingAs($auditor);
        $this->payOff(['payout_method' => 'cash'])
            ->assertForbidden();

        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 50000]);
    }

    public function test_web_payoff_posts_the_payout_and_reports_the_outcome(): void
    {
        $this->securityBalance(50000);

        $this->actingAs($this->admin)
            ->from(route('admin.members.show', $this->member))
            ->post(route('admin.security.payoff', $this->member), ['payout_method' => 'cash', 'payout_reference' => 'RECEIPT-9'])
            ->assertRedirect(route('admin.members.show', $this->member))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('security_transactions', ['transaction_type' => 'refund', 'amount' => 50000, 'payout_method' => 'cash', 'payout_reference' => 'RECEIPT-9']);
        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 0]);
    }

    public function test_web_payoff_keeps_the_security_when_the_payout_is_refused(): void
    {
        $this->securityBalance(50000);

        $this->actingAs($this->admin)
            ->from(route('admin.members.show', $this->member))
            ->post(route('admin.security.payoff', $this->member), [])
            ->assertRedirect(route('admin.members.show', $this->member))
            ->assertSessionHas('error', 'A payout method is required to refund the remaining security of TZS 50,000.00 to the member.');

        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 50000]);
    }

    public function test_web_payoff_requires_the_manage_security_permission(): void
    {
        $auditor = User::factory()->create(['branch_id' => $this->branch->id]);
        $auditor->assignRole('auditor');
        $this->securityBalance(50000);

        $this->actingAs($auditor)
            ->post(route('admin.security.payoff', $this->member), ['payout_method' => 'cash'])
            ->assertForbidden();

        $this->assertDatabaseHas('member_security_accounts', ['member_id' => $this->member->id, 'balance' => 50000]);
    }
}
