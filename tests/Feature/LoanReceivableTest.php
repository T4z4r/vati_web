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
        $product = LoanProduct::create(['name' => 'Fees Loan', 'code' => 'FEE', 'minimum_amount' => 1000, 'maximum_amount' => 2000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'repayment_frequency' => 'monthly', 'processing_fee_percentage' => 3, 'insurance_percentage' => 2, 'vat_percentage' => 1, 'security_percentage' => 10]);
        $application = LoanApplication::create(['application_number' => 'FEE-A', 'member_id' => $member->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'loan_product_id' => $product->id, 'requested_amount' => 1000000, 'duration_months' => 6, 'status' => 'submitted', 'created_by' => $user->id]);
        $loan = app(LoanApprovalService::class)->decide($application, $user, 'approved')->loan;
        $this->assertSame('840000.00', $loan->amount_receivable);
        $this->assertSame('60000.00', $loan->calc_charges);
        $product->update(['processing_fee_percentage' => 50]);
        // An old cached receivable must not allow over-disbursement.
        $loan->update(['calc_amount_receivable' => 1000000]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/loans')->assertOk()->assertJsonPath('data.0.amount_receivable', '840000.00')->assertJsonPath('data.0.charges', '60000.00');
        $this->postJson('/api/v1/loans/'.$loan->id.'/disburse', ['method' => 'cash', 'amount' => 1000000])
            ->assertCreated()->assertJsonPath('data.amount', '840000.00');
        $this->assertDatabaseHas('loan_disbursements', ['loan_id' => $loan->id, 'amount' => 840000]);
        $this->assertSame('1000000.00', $loan->fresh()->principal_amount);
        $this->assertSame('840000.00', $loan->fresh()->calc_amount_receivable);
    }

    public function test_calculator_rejects_deductions_exceeding_principal(): void
    {
        $product = new LoanProduct(['minimum_amount' => 1, 'maximum_amount' => 10000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'repayment_frequency' => 'monthly', 'processing_fee_percentage' => 80, 'security_percentage' => 30]);
        $this->expectException(\DomainException::class);
        app(LoanCalculatorService::class)->calculate($product, 1000, 6);
    }
}
