<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Region;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\LoanApprovalService;
use App\Services\LoanCalculatorService;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VatiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');
        $this->product = LoanProduct::create([
            'name' => 'Weekly Loan', 'code' => 'WEEKLY', 'minimum_amount' => 100000,
            'maximum_amount' => 5000000, 'minimum_duration_months' => 1,
            'maximum_duration_months' => 12, 'annual_interest_rate' => 24,
            'interest_method' => 'flat', 'repayment_frequency' => 'weekly',
            'security_percentage' => 10, 'processing_fee_percentage' => 2,
            'transaction_fee_percentage' => 1, 'membership_fee' => 10000,
            'vat_percentage' => 18,
        ]);
    }

    public function test_member_registration_and_duplicate_phone_validation(): void
    {
        Sanctum::actingAs($this->admin);
        $payload = ['branch_id' => $this->branch->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255712000001'];

        $this->postJson('/api/v1/members', $payload)->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.membership_number', 'VATI-M-'.now()->year.'-000001');
        $this->postJson('/api/v1/members', $payload)->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_loan_calculation_enforces_product_limits(): void
    {
        $calculator = app(LoanCalculatorService::class);
        $figures = $calculator->calculate($this->product, 1000000, 6);

        $this->assertSame(120000.0, $figures['interest']);
        $this->assertSame(1120000.0, $figures['total_repayment']);
        $this->expectException(\DomainException::class);
        $calculator->calculate($this->product, 50000, 6);
    }

    public function test_approved_loan_can_be_disbursed_paid_and_reversed(): void
    {
        $member = $this->member();
        $application = LoanApplication::create([
            'application_number' => 'VATI-LAF-'.now()->year.'-000001',
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'status' => ApplicationStatus::SUBMITTED,
            'created_by' => $this->admin->id,
        ]);

        $application = app(LoanApprovalService::class)->decide($application, $this->admin, 'approved');
        $loan = $application->loan;
        $this->assertNotNull($loan);
        $this->assertSame('pending_disbursement', $loan->status->value);

        app(DisbursementService::class)->disburse($loan, $this->admin, ['method' => 'cash', 'first_payment_date' => today()->addWeek()]);
        $loan->refresh();
        $this->assertSame('active', $loan->status->value);
        $this->assertSame($loan->number_of_installments, $loan->installments()->count());

        $before = (float) $loan->total_balance;
        $payment = app(PaymentService::class)->post($loan, $this->admin, 100000, ['payment_method' => 'cash', 'idempotency_key' => 'test-payment-1']);
        $this->assertSame($before - 100000, (float) $loan->refresh()->total_balance);
        $this->assertGreaterThan(0, $payment->allocations()->count());

        $duplicate = app(PaymentService::class)->post($loan, $this->admin, 100000, ['payment_method' => 'cash', 'idempotency_key' => 'test-payment-1']);
        $this->assertTrue($payment->is($duplicate));
        app(PaymentService::class)->reverse($payment, $this->admin, 'Incorrect collection reference');
        $this->assertSame($before, (float) $loan->refresh()->total_balance);
        $this->assertSame('reversed', $payment->refresh()->status);
    }

    public function test_branch_user_cannot_access_another_branch_member(): void
    {
        $otherArea = Area::create(['region_id' => $this->branch->area->region_id, 'name' => 'Ilala', 'code' => 'ILA']);
        $otherBranch = Branch::create(['area_id' => $otherArea->id, 'branch_code' => 'DSM-002', 'branch_name' => 'Ilala']);
        $member = $this->member($otherBranch);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        $user->assignRole('loan_officer');
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/members/{$member->id}")->assertForbidden();
    }

    private function member(?Branch $branch = null): Member
    {
        $branch ??= $this->branch;

        return Member::create([
            'membership_number' => 'VATI-M-'.now()->year.'-'.str_pad((string) (Member::count() + 1), 6, '0', STR_PAD_LEFT),
            'branch_id' => $branch->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255712'.str_pad((string) (Member::count() + 1), 6, '0', STR_PAD_LEFT),
            'created_by' => $this->admin->id,
        ]);
    }
}
