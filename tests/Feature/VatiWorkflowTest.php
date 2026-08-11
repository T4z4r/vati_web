<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\LoanApplication;
use App\Models\LoanGroupWitness;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\GroupMembershipService;
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

    private MemberGroup $group;

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
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Kinondoni Group']);
        $this->product = LoanProduct::create([
            'name' => 'Weekly Loan', 'code' => 'WEEKLY', 'minimum_amount' => 100000,
            'maximum_amount' => 5000000, 'minimum_duration_months' => 1,
            'maximum_duration_months' => 12, 'annual_interest_rate' => 24,
            'interest_method' => 'flat', 'repayment_frequency' => 'weekly',
            'security_percentage' => 10, 'processing_fee_percentage' => 2,
            'transaction_fee_percentage' => 1, 'membership_fee' => 10000,
            'vat_percentage' => 18, 'required_group_witnesses' => 2,
        ]);
    }

    public function test_member_registration_and_duplicate_phone_validation(): void
    {
        Sanctum::actingAs($this->admin);
        $payload = ['branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255712000001'];

        $this->postJson('/api/v1/members', $payload)->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.membership_number', 'VATI-M-'.now()->year.'-000001');
        $this->assertDatabaseHas('group_memberships', ['member_id' => 1, 'group_id' => $this->group->id, 'status' => 'active']);
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
            'group_id' => $this->group->id,
            'branch_id' => $this->branch->id,
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'status' => ApplicationStatus::SUBMITTED,
            'created_by' => $this->admin->id,
        ]);

        foreach ([$this->member(), $this->member()] as $witness) {
            LoanGroupWitness::create(['loan_application_id' => $application->id, 'group_id' => $this->group->id, 'member_id' => $witness->id, 'confirmed_at' => now(), 'recorded_by' => $this->admin->id]);
        }

        $application = app(LoanApprovalService::class)->decide($application, $this->admin, 'approved');
        $loan = $application->loan;
        $this->assertNotNull($loan);
        $this->assertSame('pending_disbursement', $loan->status->value);
        $this->assertSame($this->group->id, $loan->group_id);

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
        $otherGroup = MemberGroup::create(['branch_id' => $otherBranch->id, 'group_code' => 'ILA-G01', 'group_name' => 'Ilala Group']);
        $member = $this->member($otherBranch, $otherGroup);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        $user->assignRole('loan_officer');
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/members/{$member->id}")->assertForbidden();
    }

    public function test_member_requires_an_active_group_in_the_selected_branch(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/members', ['branch_id' => $this->branch->id, 'first_name' => 'No', 'last_name' => 'Group', 'phone' => '255700000001'])
            ->assertUnprocessable()->assertJsonValidationErrors('group_id');

        $otherArea = Area::create(['region_id' => $this->branch->area->region_id, 'name' => 'Temeke', 'code' => 'TEM']);
        $otherBranch = Branch::create(['area_id' => $otherArea->id, 'branch_code' => 'DSM-003', 'branch_name' => 'Temeke']);
        $otherGroup = MemberGroup::create(['branch_id' => $otherBranch->id, 'group_code' => 'TEM-G01', 'group_name' => 'Temeke Group']);
        $this->postJson('/api/v1/members', ['branch_id' => $this->branch->id, 'group_id' => $otherGroup->id, 'first_name' => 'Wrong', 'last_name' => 'Branch', 'phone' => '255700000002'])
            ->assertUnprocessable()->assertJsonValidationErrors('group_id');
    }

    public function test_application_derives_and_preserves_the_members_group_and_branch(): void
    {
        Sanctum::actingAs($this->admin);
        $member = $this->member();
        $response = $this->postJson('/api/v1/loan-applications', [
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => 999999,
            'group_id' => 999999,
            'requested_amount' => 1000000,
            'duration_months' => 6,
        ])->assertCreated();

        $application = LoanApplication::findOrFail($response->json('data.id'));
        $this->assertSame($member->branch_id, $application->branch_id);
        $this->assertSame($member->group_id, $application->group_id);
    }

    public function test_witness_rules_and_approval_witness_requirement_are_enforced(): void
    {
        Sanctum::actingAs($this->admin);
        $borrower = $this->member();
        $application = $this->application($borrower);

        $this->postJson("/api/v1/loan-applications/{$application->id}/group-witnesses", ['member_id' => $borrower->id])->assertUnprocessable();
        $first = $this->member();
        $second = $this->member();
        $this->postJson("/api/v1/loan-applications/{$application->id}/group-witnesses", ['member_id' => $first->id])->assertCreated();
        $this->postJson("/api/v1/loan-applications/{$application->id}/group-witnesses", ['member_id' => $first->id])->assertUnprocessable()->assertJsonValidationErrors('member_id');

        $this->postJson("/api/v1/loan-applications/{$application->id}/approve")->assertUnprocessable()->assertJsonPath('message', 'At least 2 confirmed group witnesses are required.');
        $this->postJson("/api/v1/loan-applications/{$application->id}/group-witnesses", ['member_id' => $second->id])->assertCreated();
        $this->postJson("/api/v1/loan-applications/{$application->id}/approve")->assertOk()->assertJsonPath('data.loan.group_id', $this->group->id);
    }

    public function test_group_transfer_preserves_membership_history_with_one_active_membership(): void
    {
        $member = $this->member();
        $newGroup = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-G02', 'group_name' => 'Second Group']);
        app(GroupMembershipService::class)->assign($member, $newGroup);

        $this->assertSame(2, $member->groupMemberships()->count());
        $this->assertSame(1, $member->groupMemberships()->where('status', 'active')->whereNull('left_at')->count());
        $this->assertSame($newGroup->id, $member->refresh()->group_id);
    }

    public function test_group_reporting_endpoints_are_available(): void
    {
        Sanctum::actingAs($this->admin);
        $this->member();
        $this->getJson("/api/v1/groups/{$this->group->id}/dashboard")->assertOk()->assertJsonPath('data.total_members', 1)->assertJsonStructure(['data' => ['par_1', 'par_7', 'par_30']]);
        $this->getJson("/api/v1/groups/{$this->group->id}/loans")->assertOk();
        $this->getJson("/api/v1/groups/{$this->group->id}/applications")->assertOk();
        $this->getJson("/api/v1/groups/{$this->group->id}/collections")->assertOk();
        $this->getJson("/api/v1/groups/{$this->group->id}/meetings")->assertOk();
    }

    private function member(?Branch $branch = null, ?MemberGroup $group = null): Member
    {
        $branch ??= $this->branch;
        $group ??= $this->group;

        $member = Member::create([
            'membership_number' => 'VATI-M-'.now()->year.'-'.str_pad((string) (Member::count() + 1), 6, '0', STR_PAD_LEFT),
            'branch_id' => $branch->id,
            'group_id' => $group->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255712'.str_pad((string) (Member::count() + 1), 6, '0', STR_PAD_LEFT),
            'created_by' => $this->admin->id,
        ]);
        GroupMembership::create(['member_id' => $member->id, 'group_id' => $group->id, 'joined_at' => today(), 'status' => 'active']);

        return $member;
    }

    private function application(Member $member): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => 'VATI-LAF-'.now()->year.'-'.str_pad((string) (LoanApplication::count() + 1), 6, '0', STR_PAD_LEFT),
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $member->group_id,
            'branch_id' => $member->branch_id,
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'status' => ApplicationStatus::SUBMITTED,
            'created_by' => $this->admin->id,
        ]);
    }
}
