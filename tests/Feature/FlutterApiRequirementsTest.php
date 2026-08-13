<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Notifications\VatiDatabaseNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlutterApiRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $creditOfficer;

    private Branch $branch;

    private Member $member;

    private MemberGroup $group;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KIN-01', 'branch_name' => 'Kinondoni']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');
        $this->creditOfficer = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->creditOfficer->assignRole('credit_officer');
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-G1', 'group_name' => 'Tumaini', 'loan_officer_id' => $this->admin->id]);
        $this->member = Member::create(['membership_number' => 'VATI-M-100', 'branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710555100', 'status' => 'active', 'created_by' => $this->admin->id]);
        GroupMembership::create(['member_id' => $this->member->id, 'group_id' => $this->group->id, 'joined_at' => today(), 'status' => 'active']);
        $this->product = LoanProduct::create(['name' => 'Biashara', 'code' => 'BIZ', 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
    }

    public function test_credit_recommendation_is_non_terminal_and_duplicate_safe(): void
    {
        $application = $this->application(ApplicationStatus::SUBMITTED);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/loan-applications/{$application->id}/assign-credit-officer", ['credit_officer_id' => $this->creditOfficer->id])
            ->assertOk()->assertJsonPath('data.status', 'credit_review');

        Sanctum::actingAs($this->creditOfficer);
        $payload = ['decision' => 'recommend', 'recommended_amount' => 90000, 'recommended_duration_months' => 6, 'overall_risk' => 'low', 'member_verified' => true, 'group_membership_verified' => true, 'documents_verified' => true];
        $this->postJson("/api/v1/loan-applications/{$application->id}/credit-review", $payload)
            ->assertOk()->assertJsonPath('data.status', 'recommended');
        $this->assertDatabaseMissing('loans', ['loan_application_id' => $application->id]);
        $this->postJson("/api/v1/loan-applications/{$application->id}/credit-review", $payload)->assertConflict();
        // API permission checks were intentionally removed; role no longer blocks this endpoint.

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/loan-applications/{$application->id}/return", ['remarks' => 'Correct the supporting income evidence.'])
            ->assertOk()->assertJsonPath('data.status', 'returned');
    }

    public function test_application_aggregate_dashboard_and_portfolio_are_mobile_ready(): void
    {
        $application = $this->application(ApplicationStatus::CREDIT_REVIEW, $this->creditOfficer->id);
        Sanctum::actingAs($this->creditOfficer);
        $this->getJson("/api/v1/loan-applications/{$application->id}")
            ->assertOk()->assertJsonPath('data.member.member_number', 'VATI-M-100')
            ->assertJsonStructure(['data' => ['member', 'group', 'assessment', 'documents', 'risk_signals', 'history']]);
        $officerDashboard = $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['credit_officer' => ['pending_credit_review', 'daily_target', 'priority_applications']]]);
        $this->assertArrayNotHasKey('management_financial_summary', $officerDashboard->json('data'));
        $this->getJson('/api/v1/portfolio/summary')->assertOk()->assertJsonStructure(['data' => ['gross_loan_portfolio', 'collection_rate', 'portfolio_at_risk']]);
        $this->getJson('/api/v1/portfolio/branches')->assertOk()->assertJsonStructure(['success', 'data', 'meta' => ['current_page', 'total'], 'links']);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonStructure([
            'data' => ['management_financial_summary' => [
                'total_loan_portfolio', 'total_posted_payments', 'repayment_profit_or_loss',
                'total_loan_disbursement', 'total_loan_applications', 'amount_requested_for_disbursement',
            ]],
        ]);
    }

    public function test_documents_can_be_uploaded_verified_downloaded_and_exported(): void
    {
        $application = $this->application();
        Sanctum::actingAs($this->admin);
        $upload = $this->post("/api/v1/loan-applications/{$application->id}/documents", [
            'document_type' => 'member_identity', 'file' => UploadedFile::fake()->create('identity.pdf', 20, 'application/pdf'), 'remarks' => 'Front and back',
        ])->assertCreated()->assertJsonPath('data.file_name', 'identity.pdf');
        $documentId = $upload->json('data.id');
        $this->postJson("/api/v1/loan-applications/{$application->id}/documents/{$documentId}/verify", ['decision' => 'verified', 'remarks' => 'Clear copy'])
            ->assertOk()->assertJsonPath('data.verification_status', 'verified');
        $this->get("/api/v1/loan-applications/{$application->id}/documents/{$documentId}/download")->assertOk();
        $this->get("/api/v1/loan-applications/{$application->id}/export")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_member_api_matches_full_web_profile_documents_and_pdf_flow(): void
    {
        Sanctum::actingAs($this->admin);
        $create = $this->post('/api/v1/members', [
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'first_name' => 'Rehema',
            'last_name' => 'Juma',
            'phone' => '255710555101',
            'photo' => UploadedFile::fake()->image('rehema.jpg', 300, 300),
            'kyc' => ['business_name' => 'Rehema Shop'],
            'nominees' => [['name' => 'Amina', 'relationship' => 'Daughter', 'percentage' => 100]],
            'family_members' => [['name' => 'Amina Juma', 'relationship' => 'Daughter', 'age' => 12]],
            'assets' => [['name' => 'Freezer', 'category' => 'Business', 'quantity' => 1, 'estimated_value' => 900000]],
        ])->assertCreated()
            ->assertJsonPath('data.kyc.business_name', 'Rehema Shop')
            ->assertJsonPath('data.family_members.0.name', 'Amina Juma')
            ->assertJsonPath('data.assets.0.name', 'Freezer')
            ->assertJsonStructure(['data' => ['photo_url']]);
        $memberId = $create->json('data.id');
        Storage::disk('public')->assertExists(Member::findOrFail($memberId)->photo_path);

        $this->putJson("/api/v1/members/{$memberId}", [
            'guardian_name' => 'Mzee Juma',
            'kyc' => ['business_name' => 'Rehema Wholesale'],
            'nominees' => [['name' => 'Amina', 'relationship' => 'Daughter', 'percentage' => 60], ['name' => 'Baraka', 'relationship' => 'Son', 'percentage' => 40]],
            'family_members' => [['name' => 'Baraka Juma', 'relationship' => 'Son', 'age' => 8]],
            'assets' => [['name' => 'Delivery Bike', 'category' => 'Business', 'quantity' => 1, 'estimated_value' => 1500000]],
        ])->assertOk()
            ->assertJsonPath('data.guardian_name', 'Mzee Juma')
            ->assertJsonPath('data.kyc.business_name', 'Rehema Wholesale')
            ->assertJsonCount(2, 'data.nominees')
            ->assertJsonPath('data.assets.0.name', 'Delivery Bike');

        $document = $this->post("/api/v1/members/{$memberId}/documents", [
            'document_type' => 'national_id',
            'file' => UploadedFile::fake()->create('nida.pdf', 20, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.file_name', 'nida.pdf');
        $documentId = $document->json('data.id');
        $this->get("/api/v1/members/{$memberId}/documents/{$documentId}/download")->assertOk();
        $this->get("/api/v1/members/{$memberId}/export")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('VATI-member-'.Member::findOrFail($memberId)->membership_number.'.pdf');
    }

    public function test_notification_read_state_and_member_photo_upload_work(): void
    {
        $this->admin->notify(new VatiDatabaseNotification(['type' => 'test_alert', 'title' => 'Review required', 'message' => 'A case needs attention.']));
        Sanctum::actingAs($this->admin);
        $list = $this->getJson('/api/v1/notifications?read=0')->assertOk()->assertJsonPath('meta.unread_count', 1);
        $id = $list->json('data.0.id');
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->post('/api/v1/members/'.$this->member->id.'/photo', ['photo' => UploadedFile::fake()->image('member.jpg', 300, 300)])
            ->assertOk()->assertJsonStructure(['data' => ['member_id', 'photo_url']]);
        Storage::disk('public')->assertExists($this->member->fresh()->photo_path);
    }

    public function test_password_recovery_is_neutral_and_revokes_existing_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        $user->createToken('old-device');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk();
        $token = Password::broker()->createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_organization_group_and_product_codes_are_generated_and_immutable(): void
    {
        Sanctum::actingAs($this->admin);
        $region = $this->postJson('/api/v1/regions', ['name' => 'Mwanza', 'code' => 'MANUAL'])
            ->assertCreated()->assertJsonPath('data.code', 'VATI-REG-'.now()->year.'-000002');
        $regionId = $region->json('data.id');
        $this->putJson("/api/v1/regions/{$regionId}", ['name' => 'Mwanza Updated', 'code' => 'CHANGED'])
            ->assertOk()->assertJsonPath('data.code', 'VATI-REG-'.now()->year.'-000002');

        $areaId = $this->postJson('/api/v1/areas', ['region_id' => $regionId, 'name' => 'Ilemela'])
            ->assertCreated()->assertJsonPath('data.code', 'VATI-AREA-'.now()->year.'-000002')->json('data.id');
        $branchId = $this->postJson('/api/v1/branches', ['area_id' => $areaId, 'branch_name' => 'Ilemela Branch'])
            ->assertCreated()->assertJsonPath('data.branch_code', 'VATI-BR-'.now()->year.'-000002')->json('data.id');
        $this->postJson('/api/v1/groups', ['branch_id' => $branchId, 'group_name' => 'Upendo Group'])
            ->assertCreated()->assertJsonPath('data.group_code', 'VATI-GRP-'.now()->year.'-000002');
        $this->postJson('/api/v1/loan-products', [
            'name' => 'Automatic Product', 'minimum_amount' => 1000, 'maximum_amount' => 500000,
            'minimum_duration_months' => 1, 'maximum_duration_months' => 12,
            'annual_interest_rate' => 20, 'interest_method' => 'flat', 'repayment_frequency' => 'weekly',
        ])->assertCreated()->assertJsonPath('data.code', 'VATI-LP-'.now()->year.'-000002');
    }

    private function application(ApplicationStatus $status = ApplicationStatus::DRAFT, ?int $assignedTo = null): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => 'VATI-LAF-'.(LoanApplication::count() + 1),
            'member_id' => $this->member->id, 'loan_product_id' => $this->product->id,
            'group_id' => $this->group->id, 'branch_id' => $this->branch->id,
            'requested_amount' => 100000, 'duration_months' => 6, 'status' => $status,
            'assigned_credit_officer_id' => $assignedTo, 'created_by' => $this->admin->id,
            'submitted_at' => $status === ApplicationStatus::DRAFT ? null : now(),
        ]);
    }
}
