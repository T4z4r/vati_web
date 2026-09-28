<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LoanApprovalService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutoLoanApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Member $member;

    private MemberGroup $group;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'AA-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'AA-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'AA-B', 'branch_name' => 'Auto Branch']);

        $this->admin = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Hadi Supervisor']);
        $this->admin->assignRole('super_admin');

        $this->group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'AA-G1', 'group_name' => 'Auto Group']);
        $this->member = Member::create([
            'membership_number' => 'AA-M1',
            'branch_id' => $branch->id,
            'group_id' => $this->group->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255711111111',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
        GroupMembership::create(['member_id' => $this->member->id, 'group_id' => $this->group->id, 'joined_at' => today(), 'status' => 'active']);
        $this->product = LoanProduct::create([
            'name' => 'Auto Loan',
            'code' => 'AUTO',
            'minimum_amount' => 1000,
            'maximum_amount' => 1000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 12,
            'annual_interest_rate' => 24,
            'repayment_frequency' => 'weekly',
            'required_group_witnesses' => 0,
            'status' => true,
        ]);
    }

    private function application(ApplicationStatus $status = ApplicationStatus::DRAFT, string $number = 'AA-APP-1'): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => $number,
            'member_id' => $this->member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $this->group->id,
            'branch_id' => $this->member->branch_id,
            'requested_amount' => 100000,
            'duration_months' => 6,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function nominate(): void
    {
        $this->member->nominees()->create([
            'name' => 'Child One',
            'relationship' => 'Child',
            'percentage' => 100,
            'attested_at' => now(),
        ]);
    }

    private function enableAutoApproval(): void
    {
        SystemSetting::set(LoanApprovalService::AUTO_APPROVAL_SETTING, true);
    }

    public function test_the_setting_is_declared_and_defaults_to_disabled(): void
    {
        $this->seed(SystemSettingSeeder::class);

        $this->assertFalse((bool) SystemSetting::get(LoanApprovalService::AUTO_APPROVAL_SETTING, true));
        $this->assertSame('boolean', SystemSetting::where('key', LoanApprovalService::AUTO_APPROVAL_SETTING)->value('type'));
    }

    public function test_a_submitted_application_stays_in_the_queue_when_the_setting_is_off(): void
    {
        SystemSetting::set(LoanApprovalService::AUTO_APPROVAL_SETTING, false);
        $this->nominate();
        $application = $this->application();

        $this->actingAs($this->admin)
            ->post(route('admin.loan-applications.submit', $application))
            ->assertRedirect()
            ->assertSessionHas('success', 'Application submitted for review.');

        $this->assertSame(ApplicationStatus::SUBMITTED, $application->refresh()->status);
        $this->assertNull($application->loan);
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_approvals', 0);
    }

    public function test_enabling_the_setting_approves_the_application_and_creates_a_loan_waiting_to_be_issued(): void
    {
        $this->enableAutoApproval();
        $this->nominate();
        $application = $this->application();

        $this->actingAs($this->admin)
            ->post(route('admin.loan-applications.submit', $application))
            ->assertRedirect()
            ->assertSessionHas('success', 'Application submitted and automatically approved. The loan account is waiting to be issued.');

        $application->refresh();
        $this->assertSame(ApplicationStatus::APPROVED, $application->status);

        $loan = $application->loan;
        $this->assertNotNull($loan);
        $this->assertSame(LoanStatus::PENDING_DISBURSEMENT, $loan->status);
        $this->assertSame(100000.0, (float) $loan->principal_amount);
        $this->assertSame($this->member->id, $loan->member_id);
        $this->assertSame($this->group->id, $loan->group_id);
        $this->assertNotEmpty($loan->loan_number);
        $this->assertGreaterThan((float) $loan->principal_amount, (float) $loan->total_balance);
        $this->assertSame(100000.0, (float) $loan->principal_balance);
    }

    public function test_the_automatic_decision_is_recorded_in_the_approval_trail(): void
    {
        $this->enableAutoApproval();
        $this->nominate();
        $application = $this->application();

        $this->actingAs($this->admin)->post(route('admin.loan-applications.submit', $application))->assertRedirect();

        $this->assertDatabaseHas('loan_approvals', [
            'loan_application_id' => $application->id,
            'user_id' => $this->admin->id,
            'decision' => 'approved',
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'remarks' => LoanApprovalService::AUTO_APPROVAL_REMARKS,
        ]);
        $this->assertDatabaseCount('loan_approvals', 1);
    }

    public function test_the_api_submission_path_auto_approves_as_well(): void
    {
        $this->enableAutoApproval();
        $this->nominate();
        $application = $this->application();
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/loan-applications/{$application->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame(ApplicationStatus::APPROVED, $application->refresh()->status);
        $this->assertSame(LoanStatus::PENDING_DISBURSEMENT, $application->loan->status);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_staff_onboarding_auto_approves_the_application_it_creates(): void
    {
        $this->enableAutoApproval();

        $this->actingAs($this->admin)
            ->post(route('admin.loan-applications.store'), [
                'member_id' => $this->member->id,
                'loan_product_id' => $this->product->id,
                'requested_amount' => 250000,
                'duration_months' => 6,
            ])
            ->assertRedirect();

        $application = LoanApplication::firstOrFail();
        $this->assertSame(ApplicationStatus::APPROVED, $application->status);
        $this->assertNotNull($application->loan);
        $this->assertSame(LoanStatus::PENDING_DISBURSEMENT, $application->loan->status);
        $this->assertSame(250000.0, (float) $application->loan->principal_amount);
    }

    public function test_the_submission_compliance_gate_still_blocks_auto_approval(): void
    {
        $this->enableAutoApproval();
        $application = $this->application();

        $this->actingAs($this->admin)
            ->post(route('admin.loan-applications.submit', $application))
            ->assertRedirect()
            ->assertSessionHas('error', 'Nominee allocations must total exactly 100%.');

        $this->assertSame(ApplicationStatus::DRAFT, $application->refresh()->status);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_manual_approval_still_works_and_creates_only_one_loan(): void
    {
        SystemSetting::set(LoanApprovalService::AUTO_APPROVAL_SETTING, false);
        $this->nominate();
        $application = $this->application();

        $this->actingAs($this->admin)->post(route('admin.loan-applications.submit', $application))->assertRedirect();
        $this->assertSame(ApplicationStatus::SUBMITTED, $application->refresh()->status);

        $this->enableAutoApproval();
        $this->actingAs($this->admin)
            ->post(route('admin.loan-applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('success', 'Application approved and loan account created.');

        $this->assertSame(ApplicationStatus::APPROVED, $application->refresh()->status);
        $this->assertDatabaseCount('loans', 1);
    }

    public function test_an_auto_approved_application_cannot_be_approved_again(): void
    {
        $this->enableAutoApproval();
        $this->nominate();
        $application = $this->application();

        $this->actingAs($this->admin)->post(route('admin.loan-applications.submit', $application))->assertRedirect();
        $this->assertDatabaseCount('loans', 1);

        $this->actingAs($this->admin)
            ->post(route('admin.loan-applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('error', 'This application cannot be decided in its current state.');

        $this->assertSame(ApplicationStatus::APPROVED, $application->refresh()->status);
        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('loan_approvals', 1);
    }
}
