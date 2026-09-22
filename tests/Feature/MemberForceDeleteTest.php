<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function seedWorld(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Kinondoni Group']);
        $other = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G02', 'group_name' => 'Second Group']);
        $product = LoanProduct::create(['name' => 'Weekly Loan', 'code' => 'WEEKLY', 'minimum_amount' => 100000, 'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'interest_method' => 'flat', 'repayment_frequency' => 'weekly', 'security_percentage' => 10, 'processing_fee_percentage' => 1, 'vat_percentage' => 0.18, 'required_group_witnesses' => 2]);

        $member = Member::create([
            'branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'DSM-001', 'first_name' => 'Asha', 'middle_name' => 'Musa', 'last_name' => 'Juma',
            'guardian_name' => 'Juma Musa', 'phone' => '255712345678', 'national_id' => '19900101-12345-00001-00', 'gender' => 'Female',
            'occupation' => 'Trader', 'physical_address' => 'Kinondoni', 'admission_date' => today(), 'passbook_issue_date' => today(),
            'created_by' => $admin->id,
        ]);

        $otherMember = Member::create([
            'branch_id' => $branch->id, 'group_id' => $other->id, 'membership_number' => 'DSM-002', 'first_name' => 'Binti', 'middle_name' => 'Juma', 'last_name' => 'Musa',
            'phone' => '255712345679', 'gender' => 'Female', 'occupation' => 'Trader', 'physical_address' => 'Kinondoni',
            'admission_date' => today(), 'created_by' => $admin->id,
        ]);

        return [$admin, $branch, $member, $group, $other, $product, $otherMember];
    }

    private function attachFullData(int $memberId, int $branchId, int $groupId, int $otherGroupId, int $otherMemberId, int $productId): array
    {
        DB::table('group_memberships')->insert(['member_id' => $memberId, 'group_id' => $groupId, 'joined_at' => today(), 'status' => 'active']);
        DB::table('member_kycs')->insert(['member_id' => $memberId, 'mpesa_phone' => '255712345678', 'business_name' => 'Asha Shop', 'household_monthly_income' => 500000]);
        DB::table('member_nominees')->insert(['member_id' => $memberId, 'name' => 'Child', 'relationship' => 'Child', 'percentage' => 100]);
        DB::table('member_family_members')->insert(['member_id' => $memberId, 'name' => 'Juma Musa', 'gender' => 'Male', 'age' => 40, 'relationship' => 'Father']);
        $assetTypeId = DB::table('asset_types')->insertGetId(['name' => 'Sofa', 'category' => 'Household']);
        DB::table('member_assets')->insert(['member_id' => $memberId, 'asset_type_id' => $assetTypeId, 'quantity' => 1, 'estimated_value' => 200000]);
        $accountId = DB::table('member_security_accounts')->insertGetId(['member_id' => $memberId, 'balance' => 50000]);
        DB::table('security_transactions')->insert(['transaction_number' => 'SEC-001', 'member_security_account_id' => $accountId, 'transaction_type' => 'deposit', 'amount' => 50000, 'balance_before' => 0, 'balance_after' => 50000, 'transaction_date' => now()]);
        DB::table('passbook_replacements')->insert(['member_id' => $memberId, 'reason' => 'Lost passbook', 'fee_amount' => 1000]);
        DB::table('payment_transactions')->insert(['provider' => 'vodacom', 'transaction_type' => 'deposit', 'reference' => 'PTX-001', 'member_id' => $memberId, 'amount' => 50000, 'status' => 'success', 'paid_at' => now()]);

        $applicationId = DB::table('loan_applications')->insertGetId([
            'application_number' => 'APP-001', 'member_id' => $memberId, 'loan_product_id' => $productId, 'group_id' => $groupId,
            'branch_id' => $branchId, 'application_type' => 'main', 'requested_amount' => 600000, 'duration_months' => 6,
            'loan_purpose' => 'Restock', 'status' => 'disbursed', 'created_by' => 1, 'submitted_at' => now(),
        ]);
        DB::table('loan_utilizations')->insert(['loan_application_id' => $applicationId, 'purpose' => 'Working capital', 'allocation_amount' => 300000]);
        DB::table('loan_assessments')->insert(['loan_application_id' => $applicationId, 'core_business_income' => 500000, 'monthly_profit' => 300000, 'disposable_income' => 200000]);
        DB::table('loan_guarantors')->insert(['loan_application_id' => $applicationId, 'guarantor_type' => 'group', 'name' => 'Binti Juma', 'relationship' => 'Peer']);
        $docId = DB::table('loan_documents')->insertGetId(['loan_application_id' => $applicationId, 'document_type' => 'business_license', 'file_path' => 'loan-compliance/documents/doc.pdf']);
        DB::table('loan_approvals')->insert(['loan_application_id' => $applicationId, 'user_id' => 1, 'role' => 'branch_manager', 'decision' => 'approved', 'from_status' => 'submitted', 'to_status' => 'approved', 'acted_at' => now()]);
        DB::table('credit_reviews')->insert(['loan_application_id' => $applicationId, 'attempt' => 1, 'decision' => 'approved', 'overall_risk' => 'low', 'reviewed_by' => 1, 'reviewed_at' => now()]);

        $loanId = DB::table('loans')->insertGetId([
            'loan_number' => 'LOAN-001', 'loan_application_id' => $applicationId, 'member_id' => $memberId, 'group_id' => $groupId,
            'loan_product_id' => $productId, 'branch_id' => $branchId, 'principal_amount' => 600000, 'interest_amount' => 50000,
            'total_repayment' => 650000, 'principal_balance' => 400000, 'interest_balance' => 20000, 'total_balance' => 420000,
            'number_of_installments' => 6, 'installment_amount' => 108333, 'status' => 'active',
        ]);
        $installmentId = DB::table('loan_installments')->insertGetId(['loan_id' => $loanId, 'installment_number' => 1, 'due_date' => today()->addWeek(), 'principal_due' => 100000, 'interest_due' => 8333, 'total_due' => 108333, 'status' => 'upcoming']);
        DB::table('loan_disbursements')->insert(['loan_id' => $loanId, 'amount' => 600000, 'method' => 'mpesa', 'status' => 'completed', 'disbursed_at' => now()]);
        DB::table('loan_default_notices')->insert(['loan_id' => $loanId, 'notice_days' => 14, 'issued_at' => now(), 'expires_at' => now()->addDays(14), 'delivery_method' => 'sms', 'notice_text' => 'Pay up']);
        DB::table('loan_settlements')->insert(['settlement_number' => 'SET-001', 'loan_id' => $loanId, 'settlement_date' => today(), 'principal_outstanding' => 100000, 'interest_outstanding' => 5000]);
        DB::table('loan_clearances')->insert(['loan_id' => $loanId, 'loan_outstanding_amount' => 0, 'status' => 'pending']);
        DB::table('loan_cycles')->insert(['loan_id' => $loanId, 'cycle_type' => 'main', 'is_main_cycle' => true, 'status' => 'active', 'principal_amount' => 600000, 'interest_rate' => 24, 'total_installments' => 6, 'weekly_installment' => 108333, 'total_with_interest' => 650000]);
        DB::table('loan_security_transactions')->insert(['loan_id' => $loanId, 'transaction_date' => today(), 'security_amount' => 50000, 'balance' => 50000]);
        $cycleId = DB::table('loan_cycles')->where('loan_id', $loanId)->value('id');
        DB::table('loan_installment_records')->insert(['loan_id' => $loanId, 'loan_cycle_id' => $cycleId, 'installment_number' => 1, 'payment_date' => today()->addWeek(), 'principal_amount' => 100000, 'interest_amount' => 8333, 'total_amount' => 108333, 'is_paid' => true, 'actual_payment_date' => today()]);
        DB::table('loan_refinancings')->insert(['old_loan_id' => $loanId, 'new_loan_id' => $loanId, 'old_outstanding_balance' => 420000, 'new_principal_amount' => 500000, 'net_disbursement_amount' => 80000, 'processed_at' => now()]);

        $paymentId = DB::table('payments')->insertGetId([
            'payment_number' => 'PAY-001', 'member_id' => $memberId, 'loan_id' => $loanId, 'branch_id' => $branchId,
            'amount' => 108333, 'payment_method' => 'mpesa', 'paid_at' => now(),
        ]);
        DB::table('payment_allocations')->insert(['payment_id' => $paymentId, 'loan_installment_id' => $installmentId, 'principal_amount' => 100000, 'interest_amount' => 8333]);
        DB::table('security_transactions')->insert(['transaction_number' => 'SEC-002', 'member_security_account_id' => $accountId, 'loan_id' => $loanId, 'transaction_type' => 'withdrawal', 'amount' => 5000, 'balance_before' => 50000, 'balance_after' => 45000, 'transaction_date' => now()]);

        // The member signed as a witness on another member's application.
        $witnessAppId = DB::table('loan_applications')->insertGetId([
            'application_number' => 'APP-002', 'member_id' => $otherMemberId, 'loan_product_id' => $productId, 'group_id' => $otherGroupId,
            'branch_id' => $branchId, 'application_type' => 'main', 'requested_amount' => 300000, 'duration_months' => 3,
            'loan_purpose' => 'Trade', 'status' => 'submitted',
        ]);
        DB::table('loan_group_witnesses')->insert(['loan_application_id' => $witnessAppId, 'group_id' => $otherGroupId, 'member_id' => $memberId, 'confirmed_at' => now()]);

        return ['application_id' => $applicationId, 'loan_id' => $loanId, 'account_id' => $accountId, 'witness_application_id' => $witnessAppId, 'document_id' => $docId];
    }

    private function assertMemberDataGone(int $memberId): void
    {
        $this->assertDatabaseMissing('members', ['id' => $memberId]);
        $this->assertDatabaseMissing('group_memberships', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('member_kycs', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('member_nominees', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('member_family_members', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('member_assets', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('member_security_accounts', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('passbook_replacements', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('payment_transactions', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('loan_applications', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('loans', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('payments', ['member_id' => $memberId]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $memberId, 'notifiable_type' => Member::class]);
    }

    public function test_force_delete_removes_member_and_all_linked_data_via_api(): void
    {
        [$admin, $branch, $member, $group, $other, $product, $otherMember] = $this->seedWorld();
        $ids = $this->attachFullData($member->id, $branch->id, $group->id, $other->id, $otherMember->id, $product->id);

        $this->postJson("/api/v1/members/{$member->id}/delete?force=1")
            ->assertNoContent();

        $this->assertDatabaseMissing('loan_utilizations', ['loan_application_id' => $ids['application_id']]);
        $this->assertDatabaseMissing('loan_assessments', ['loan_application_id' => $ids['application_id']]);
        $this->assertDatabaseMissing('loan_guarantors', ['loan_application_id' => $ids['application_id']]);
        $this->assertDatabaseMissing('loan_documents', ['loan_application_id' => $ids['application_id']]);
        $this->assertDatabaseMissing('loan_approvals', ['loan_application_id' => $ids['application_id']]);
        $this->assertDatabaseMissing('credit_reviews', ['loan_application_id' => $ids['application_id']]);
        $this->assertDatabaseMissing('loan_installments', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_disbursements', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_default_notices', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_settlements', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_clearances', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_cycles', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_security_transactions', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_installment_records', ['loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('loan_refinancings', ['old_loan_id' => $ids['loan_id']]);
        $this->assertDatabaseMissing('security_transactions', ['member_security_account_id' => $ids['account_id']]);
        $this->assertDatabaseMissing('payment_allocations', ['payment_id' => DB::table('payments')->where('member_id', $member->id)->value('id')]);
        $this->assertDatabaseMissing('loan_group_witnesses', ['member_id' => $member->id]);
        // The application the member witnessed for is kept, but the witness row is gone.
        $this->assertDatabaseHas('loan_applications', ['id' => $ids['witness_application_id']]);

        $this->assertMemberDataGone($member->id);
        $this->assertTrue(Member::withTrashed()->find($member->id) === null);
    }

    public function test_force_delete_removes_member_without_loans_via_web_route(): void
    {
        [$admin, $branch, $member, $group] = $this->seedWorld();

        DB::table('group_memberships')->insert(['member_id' => $member->id, 'group_id' => $group->id, 'joined_at' => today(), 'status' => 'active']);
        DB::table('member_kycs')->insert(['member_id' => $member->id, 'business_name' => 'Shop']);

        $this->actingAs($admin)
            ->delete(route('admin.members.destroy', $member->id), ['_force' => 1])
            ->assertRedirect(route('admin.members.index'));

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('group_memberships', ['member_id' => $member->id]);
        $this->assertDatabaseMissing('member_kycs', ['member_id' => $member->id]);
        $this->assertTrue(Member::withTrashed()->find($member->id) === null);
    }

    public function test_force_delete_cleans_up_stored_files(): void
    {
        Storage::fake('public');
        Storage::fake('signatures');

        [$admin, $branch, $member, $group] = $this->seedWorld();

        $member->update(['photo_path' => 'members/' . $member->id . '/photo.jpg']);
        Storage::disk('public')->put($member->photo_path, 'photo');
        $docPath = 'member_documents/' . $member->id . '/id.pdf';
        Storage::disk('public')->put($docPath, 'pdf');
        $member->documents()->create([
            'document_type' => 'national_id', 'file_name' => 'id.pdf', 'file_path' => $docPath,
            'mime_type' => 'application/pdf', 'file_size' => 3, 'uploaded_by' => $admin->id, 'disk' => 'public',
        ]);
        $sigPath = 'members/' . $member->id . '/signatures/sig.png';
        Storage::disk('signatures')->put($sigPath, 'png');
        $member->documents()->create([
            'document_type' => 'signature', 'file_name' => 'sig.png', 'file_path' => $sigPath,
            'mime_type' => 'image/png', 'file_size' => 3, 'uploaded_by' => $admin->id, 'disk' => 'signatures',
            'sha256' => str_repeat('a', 64), 'active_signature_member_id' => $member->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.members.destroy', $member->id), ['_force' => 1])
            ->assertRedirect(route('admin.members.index'));

        $this->assertDatabaseMissing('member_documents', ['member_id' => $member->id]);
        Storage::disk('public')->assertMissing($member->photo_path);
        Storage::disk('public')->assertMissing($docPath);
        Storage::disk('signatures')->assertMissing($sigPath);
    }

    public function test_soft_delete_still_blocks_members_with_loan_history(): void
    {
        [$admin, $branch, $member, $group, $other, $product, $otherMember] = $this->seedWorld();
        $this->attachFullData($member->id, $branch->id, $group->id, $other->id, $otherMember->id, $product->id);

        $this->actingAs($admin)
            ->from(route('admin.members.show', $member->id))
            ->delete(route('admin.members.destroy', $member->id))
            ->assertRedirect(route('admin.members.show', $member->id))
            ->assertSessionHas('error', 'This member has loan history and cannot be deleted.');

        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }
}