<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanTerm;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\ApplicationComplianceService;
use App\Services\DisbursementService;
use App\Services\LoanAdministrationService;
use App\Services\LoanCancellationService;
use App\Services\SettlementService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Member $member;

    private MemberGroup $group;

    private LoanProduct $product;

    private LoanTerm $term;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Ubungo']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'UBG-01', 'branch_name' => 'Ubungo']);
        $this->admin = User::factory()->create(['branch_id' => $branch->id]);
        $this->admin->assignRole('super_admin');
        $this->group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'UBG-G1', 'group_name' => 'Ubungo Group']);
        $this->member = Member::create(['membership_number' => 'VATI-M-TEST', 'branch_id' => $branch->id, 'group_id' => $this->group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $this->admin->id]);
        GroupMembership::create(['member_id' => $this->member->id, 'group_id' => $this->group->id, 'joined_at' => today(), 'status' => 'active']);
        $this->product = LoanProduct::create(['name' => 'Test Loan', 'code' => 'TEST', 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
        $this->term = LoanTerm::create(['version' => 'TEST-1', 'title' => 'Test terms', 'body' => 'The applicant accepts the loan declaration.', 'effective_from' => today(), 'is_active' => true]);
        Sanctum::actingAs($this->admin);
    }

    public function test_submission_allows_missing_applicant_evidence_but_approval_still_requires_it(): void
    {
        $application = $this->application();

        $this->postJson("/api/v1/loan-applications/{$application->id}/submit")->assertUnprocessable();

        foreach (['family', 'non_family'] as $index => $type) {
            $this->post("/api/v1/loan-applications/{$application->id}/compliance/guarantors", [
                'guarantor_type' => $type, 'name' => "Guarantor {$index}", 'relationship' => 'Relative',
                'phone' => "25572000000{$index}", 'accept_declaration' => '1',
                'signature' => UploadedFile::fake()->image("signature-{$index}.png"),
                'thumbprint' => UploadedFile::fake()->image("thumbprint-{$index}.png"),
                'joint_photo' => UploadedFile::fake()->image("photo-{$index}.jpg"),
            ])->assertCreated();
        }

        $this->putJson("/api/v1/loan-applications/{$application->id}/compliance/nominees", ['nominees' => [
            ['name' => 'Child One', 'relationship' => 'Child', 'percentage' => 60],
            ['name' => 'Child Two', 'relationship' => 'Child', 'percentage' => 40],
        ]])->assertOk();

        foreach (['member_identity', 'guarantor_identity'] as $type) {
            $response = $this->post("/api/v1/loan-applications/{$application->id}/compliance/documents", ['document_type' => $type, 'document' => UploadedFile::fake()->create("{$type}.pdf", 20, 'application/pdf')])->assertCreated();
            $documentId = $response->json('data.id');
            $this->postJson("/api/v1/loan-applications/{$application->id}/compliance/documents/{$documentId}/verify", ['decision' => 'verified'])->assertOk();
        }

        $this->postJson("/api/v1/loan-applications/{$application->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->assertNull($application->refresh()->consented_at);
        $this->assertNull($application->applicant_signature_path);
        $this->assertNull($application->applicant_thumbprint_path);
        $this->assertSame(100.0, (float) $this->member->nominees()->sum('percentage'));
        $this->assertSame(2, $application->guarantors()->count());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Applicant consent, signature, thumbprint, and versioned loan terms are required before approval.');
        app(ApplicationComplianceService::class)->assertReadyForApproval($application);
    }

    public function test_cooling_off_period_blocks_disbursement_and_allows_cancellation(): void
    {
        $application = $this->application(ApplicationStatus::APPROVED);
        $this->makeCompliant($application, false);
        $loan = $this->loan($application, LoanStatus::PENDING_DISBURSEMENT);

        $this->expectException(DomainException::class);
        app(DisbursementService::class)->disburse($loan, $this->admin, ['method' => 'cash']);
    }

    public function test_cancellation_passbook_notice_and_manager_clearance_are_recorded(): void
    {
        $application = $this->application(ApplicationStatus::APPROVED);
        $this->makeCompliant($application, false);
        app(LoanCancellationService::class)->cancel($application, $this->admin, 'Applicant changed plans');
        $this->assertSame(ApplicationStatus::CANCELLED, $application->refresh()->status);

        $replacement = app(LoanAdministrationService::class)->replacePassbook($this->member, $this->admin, ['reason' => 'lost', 'payment_reference' => 'RCPT-1000']);
        $this->assertSame(1000.0, (float) $replacement->fee_amount);

        $secondApplication = $this->application(ApplicationStatus::APPROVED, 'VATI-LAF-2');
        $loan = $this->loan($secondApplication, LoanStatus::ACTIVE);
        $notice = app(LoanAdministrationService::class)->issueDefaultNotice($loan, $this->admin, ['delivery_method' => 'hand']);
        $this->assertEquals(14, $notice->issued_at->diffInDays($notice->expires_at));

        app(SettlementService::class)->settle($loan, $this->admin, ['cash_payment' => 1200]);
        $this->assertSame('pending', $loan->clearance()->firstOrFail()->status);
        $clearance = app(LoanAdministrationService::class)->authorizeClearance($loan->refresh(), $this->admin, ['comments' => 'No dues remain.'], UploadedFile::fake()->image('manager-signature.png'));
        $this->assertSame('authorized', $clearance->status);
        $this->assertNotNull($clearance->authorized_at);
    }

    private function application(ApplicationStatus $status = ApplicationStatus::DRAFT, string $number = 'VATI-LAF-1'): LoanApplication
    {
        return LoanApplication::create(['application_number' => $number, 'member_id' => $this->member->id, 'loan_product_id' => $this->product->id, 'group_id' => $this->group->id, 'branch_id' => $this->member->branch_id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => $status, 'created_by' => $this->admin->id]);
    }

    private function makeCompliant(LoanApplication $application, bool $expired): void
    {
        $application->update(['loan_term_id' => $this->term->id, 'consent_declaration' => $this->term->body, 'consented_at' => now(), 'cancellation_deadline' => $expired ? now()->subMinute() : now()->addDays(3), 'applicant_signature_path' => 'signature.png', 'applicant_thumbprint_path' => 'thumbprint.png']);
    }

    private function loan(LoanApplication $application, LoanStatus $status): Loan
    {
        return Loan::create(['loan_number' => 'VATI-L-'.(Loan::count() + 1), 'loan_application_id' => $application->id, 'member_id' => $this->member->id, 'group_id' => $this->group->id, 'loan_product_id' => $this->product->id, 'branch_id' => $this->member->branch_id, 'principal_amount' => 1000, 'interest_amount' => 200, 'total_repayment' => 1200, 'principal_balance' => 1000, 'interest_balance' => 200, 'total_balance' => 1200, 'number_of_installments' => 1, 'installment_amount' => 1200, 'status' => $status]);
    }
}
