<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\LoanApplication;
use App\Models\LoanGroupWitness;
use App\Models\LoanProduct;
use App\Models\LoanTerm;
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

    private LoanTerm $term;

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
            'security_percentage' => 10, 'processing_fee_percentage' => 1,
            'insurance_percentage' => 1.5, 'transaction_fee_percentage' => 0, 'membership_fee' => 0,
            'vat_percentage' => 0.18, 'required_group_witnesses' => 2,
        ]);
        $this->term = LoanTerm::create(['version' => 'TEST-1', 'title' => 'Test terms', 'body' => 'Test declaration', 'effective_from' => today(), 'is_active' => true]);
    }

    public function test_member_registration_and_duplicate_phone_validation(): void
    {
        Sanctum::actingAs($this->admin);
        $payload = [
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'first_name' => 'Asha',
            'middle_name' => 'Juma',
            'last_name' => 'Musa',
            'guardian_name' => 'Juma Musa',
            'phone' => '255712000001',
            'alternate_phone' => '255712000002',
            'national_id' => '19900101-12345-00001-00',
            'voter_id' => 'VOTER-001',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Female',
            'marital_status' => 'Married',
            'occupation' => 'Trader',
            'nationality' => 'Tanzanian',
            'physical_address' => 'Kinondoni, Dar es Salaam',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'street' => 'Ali Hassan Mwinyi Road',
            'admission_date' => today()->toDateString(),
            'passbook_issue_date' => today()->toDateString(),
            'kyc' => [
                'mpesa_phone' => '255712000001',
                'bank_account_number' => '00123456789',
                'bank_account_name' => 'Asha Juma Musa',
                'bank_name' => 'VATI Test Bank',
                'house_number' => 'KJ-42',
                'police_station' => 'Kijitonyama',
                'business_name' => 'Asha Produce',
                'business_type' => 'Food trading',
                'business_address' => 'Kijitonyama Market',
                'household_monthly_income' => 800000,
                'household_monthly_expenses' => 300000,
                'number_of_dependants' => 2,
                'head_of_household' => 'Asha Juma Musa',
                'house_ownership_status' => 'Owned',
                'house_roof_type' => 'Iron sheets',
                'house_fence_type' => 'Block wall',
            ],
            'nominees' => [
                ['name' => 'Child One', 'relationship' => 'Child', 'percentage' => 60],
                ['name' => 'Child Two', 'relationship' => 'Child', 'percentage' => 40],
            ],
            'family_members' => [
                ['name' => 'Juma Musa Junior', 'gender' => 'Male', 'age' => 10, 'relationship' => 'Son'],
            ],
            'assets' => [
                ['name' => 'Radio', 'category' => 'Household', 'quantity' => 1, 'estimated_value' => 80000],
            ],
        ];

        $response = $this->postJson('/api/v1/members', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.membership_number', 'VATI-M-'.now()->year.'-000001')
            ->assertJsonPath('data.guardian_name', 'Juma Musa')
            ->assertJsonPath('data.kyc.house_number', 'KJ-42')
            ->assertJsonPath('data.nominees.1.percentage', '40.00')
            ->assertJsonPath('data.family_members.0.name', 'Juma Musa Junior')
            ->assertJsonPath('data.assets.0.name', 'Radio')
            ->assertJsonPath('data.issued_by.id', $this->admin->id)
            ->assertJsonCount(2, 'data.nominees')
            ->assertJsonCount(0, 'data.loans');
        $this->assertDatabaseHas('group_memberships', ['member_id' => 1, 'group_id' => $this->group->id, 'status' => 'active']);
        $this->assertDatabaseHas('member_kycs', ['member_id' => 1, 'house_number' => 'KJ-42', 'number_of_dependants' => 2]);
        $this->assertDatabaseCount('member_nominees', 2);
        $this->assertDatabaseHas('member_family_members', ['member_id' => 1, 'name' => 'Juma Musa Junior']);
        $this->assertDatabaseHas('member_assets', ['member_id' => 1, 'quantity' => 1, 'estimated_value' => 80000]);
        $this->getJson('/api/v1/members/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.group.meeting_day', null)
            ->assertJsonPath('data.kyc.business_name', 'Asha Produce')
            ->assertJsonPath('data.family_members.0.name', 'Juma Musa Junior')
            ->assertJsonPath('data.assets.0.name', 'Radio')
            ->assertJsonCount(2, 'data.nominees');
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

        $this->makeCompliant($application);

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

        $finalPayment = app(PaymentService::class)->post($loan, $this->admin, $before, ['payment_method' => 'cash', 'idempotency_key' => 'test-final-payment']);
        $this->assertSame(0.0, (float) $loan->refresh()->total_balance);
        $this->assertSame(LoanStatus::SETTLED, $loan->status);
        $this->assertSame($loan->number_of_installments, $loan->installments()->where('status', 'paid')->count());

        app(PaymentService::class)->reverse($finalPayment, $this->admin, 'Final payment entered incorrectly');
        $this->assertSame($before, (float) $loan->refresh()->total_balance);
        $this->assertContains($loan->status, [LoanStatus::ACTIVE, LoanStatus::OVERDUE]);
        $this->assertSame('reversed', $payment->refresh()->status);

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.loan_applications.0.application_number', $application->application_number)
            ->assertJsonPath('data.loans.0.loan_number', $loan->loan_number)
            ->assertJsonPath('data.loans.0.status', 'active')
            ->assertJsonPath('data.loans.0.payments.0.status', 'reversed')
            ->assertJsonCount($loan->number_of_installments, 'data.loans.0.installments');
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

        $inactive = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-INACTIVE', 'group_name' => 'Inactive Group', 'status' => false]);
        $this->postJson('/api/v1/members', ['branch_id' => $this->branch->id, 'group_id' => $inactive->id, 'first_name' => 'Inactive', 'last_name' => 'Group', 'phone' => '255700000003'])
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
        $otherGroup = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-G03', 'group_name' => 'Unrelated Group']);
        $outsider = $this->member($this->branch, $otherGroup);
        $this->postJson("/api/v1/loan-applications/{$application->id}/group-witnesses", ['member_id' => $outsider->id])->assertUnprocessable();
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

    public function test_branch_user_cannot_create_an_application_for_another_branch_member(): void
    {
        $otherArea = Area::create(['region_id' => $this->branch->area->region_id, 'name' => 'Ubungo', 'code' => 'UBG']);
        $otherBranch = Branch::create(['area_id' => $otherArea->id, 'branch_code' => 'DSM-004', 'branch_name' => 'Ubungo']);
        $otherGroup = MemberGroup::create(['branch_id' => $otherBranch->id, 'group_code' => 'UBG-G01', 'group_name' => 'Ubungo Group']);
        $member = $this->member($otherBranch, $otherGroup);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        $user->assignRole('loan_officer');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loan-applications', ['member_id' => $member->id, 'loan_product_id' => $this->product->id, 'requested_amount' => 1000000, 'duration_months' => 6])->assertForbidden();
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
        $application = LoanApplication::create([
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

        $this->makeCompliant($application);

        return $application;
    }

    private function makeCompliant(LoanApplication $application): void
    {
        $application->update([
            'loan_term_id' => $this->term->id,
            'consent_declaration' => $this->term->body,
            'consented_at' => now()->subDays(4),
            'cancellation_deadline' => now()->subDay(),
            'applicant_signature_path' => 'tests/applicant-signature.png',
            'applicant_thumbprint_path' => 'tests/applicant-thumbprint.png',
        ]);
        foreach (['family', 'non_family'] as $index => $type) {
            $application->guarantors()->create([
                'guarantor_type' => $type, 'name' => "Guarantor {$index}", 'relationship' => 'Relative',
                'phone' => "25570000000{$index}", 'signature_path' => 'tests/signature.png',
                'thumbprint_path' => 'tests/thumbprint.png', 'joint_photo_path' => 'tests/photo.png',
                'declaration_text' => 'Accepted', 'declaration_accepted_at' => now(),
            ]);
        }
        $application->member->nominees()->create(['name' => 'Nominee', 'relationship' => 'Child', 'percentage' => 100, 'attested_at' => now()]);
        foreach (['member_identity', 'guarantor_identity'] as $type) {
            $application->documents()->create(['document_type' => $type, 'file_path' => "tests/{$type}.pdf", 'is_required' => true, 'verification_status' => 'verified', 'uploaded_by' => $this->admin->id, 'verified_by' => $this->admin->id, 'verified_at' => now()]);
        }
    }
}
