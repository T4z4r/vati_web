<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_and_member_can_be_onboarded_with_kyc_membership_and_nominees(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Ubungo', 'code' => 'UBG']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'UBG-01', 'branch_name' => 'Ubungo']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $groupResponse = $this->postJson('/api/v1/onboarding/groups', [
            'branch_id' => $branch->id,
            'group_code' => 'UBG-G01',
            'group_name' => 'Ubungo Market Group',
            'meeting_day' => 'Tuesday',
            'meeting_time' => '09:30',
            'location' => 'Ubungo Market',
            'ward' => 'Ubungo',
        ])->assertCreated()->assertJsonPath('message', 'Group onboarding completed.');
        $groupId = $groupResponse->json('data.id');

        $memberResponse = $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id,
            'group_id' => $groupId,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'guardian_name' => 'Musa Juma',
            'phone' => '255712345678',
            'national_id' => '19900101-12345-00001-00',
            'gender' => 'Female',
            'occupation' => 'Trader',
            'physical_address' => 'Ubungo, Dar es Salaam',
            'admission_date' => today()->toDateString(),
            'passbook_issue_date' => today()->toDateString(),
            'kyc' => [
                'mpesa_phone' => '255712345678',
                'business_name' => 'Asha Produce',
                'business_type' => 'Food trading',
                'household_monthly_income' => 800000,
                'household_monthly_expenses' => 300000,
                'number_of_dependants' => 2,
            ],
            'nominees' => [
                ['name' => 'Child One', 'relationship' => 'Child', 'percentage' => 60],
                ['name' => 'Child Two', 'relationship' => 'Child', 'percentage' => 40],
            ],
            'family_members' => [
                ['name' => 'Juma Musa', 'gender' => 'Male', 'age' => 14, 'relationship' => 'Son', 'education' => 'Secondary'],
            ],
            'assets' => [
                ['name' => 'Sofa', 'category' => 'Household', 'quantity' => 1, 'estimated_value' => 500000],
            ],
        ])->assertCreated()->assertJsonPath('message', 'Member onboarding completed.')
            ->assertJsonPath('data.group.id', $groupId)
            ->assertJsonPath('data.kyc.business_name', 'Asha Produce');

        $memberId = $memberResponse->json('data.id');
        $this->assertDatabaseHas('group_memberships', ['member_id' => $memberId, 'group_id' => $groupId, 'status' => 'active']);
        $this->assertDatabaseHas('member_nominees', ['member_id' => $memberId, 'percentage' => 60]);
        $this->assertCount(2, $memberResponse->json('data.nominees'));
        $this->assertDatabaseHas('member_family_members', ['member_id' => $memberId, 'name' => 'Juma Musa']);
        $this->assertDatabaseHas('asset_types', ['name' => 'Sofa', 'category' => 'Household']);
        $this->assertDatabaseHas('member_assets', ['member_id' => $memberId, 'quantity' => 1, 'estimated_value' => 500000]);
        $this->assertSame('Juma Musa', $memberResponse->json('data.family_members.0.name'));
        $this->assertSame('Sofa', $memberResponse->json('data.assets.0.name'));

        $product = LoanProduct::create([
            'name' => 'Weekly Business Loan', 'code' => 'ONBOARD-WEEKLY', 'minimum_amount' => 100000,
            'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12,
            'annual_interest_rate' => 24, 'interest_method' => 'flat', 'repayment_frequency' => 'weekly', 'status' => true,
        ]);
        $applicationResponse = $this->postJson('/api/v1/loan-applications', [
            'member_id' => $memberId,
            'loan_product_id' => $product->id,
            'application_type' => 'main',
            'requested_amount' => 600000,
            'duration_months' => 6,
            'loan_purpose' => 'Increase produce inventory',
            'assessment' => [
                'core_business_income' => 800000,
                'other_income' => 100000,
                'business_expenses' => 300000,
                'household_expenses' => 200000,
                'existing_external_debt' => 0,
            ],
            'utilizations' => [
                ['purpose' => 'Working capital', 'allocation_amount' => 500000, 'current_asset_value' => 200000],
                ['purpose' => 'Equipment', 'allocation_amount' => 100000, 'current_asset_value' => 0],
            ],
        ])->assertCreated()->assertJsonPath('message', 'Loan application created.')
            ->assertJsonPath('data.member.id', $memberId)
            ->assertJsonPath('data.group.id', $groupId)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requirements.submission.attachments_required', false)
            ->assertJsonPath('data.requirements.approval.complete_guarantors_required', 2);

        $applicationId = $applicationResponse->json('data.id');
        $this->assertDatabaseHas('loan_applications', ['id' => $applicationId, 'branch_id' => $branch->id, 'group_id' => $groupId, 'business_summary' => null]);
        $this->assertDatabaseCount('loan_utilizations', 2);
        $this->assertGreaterThan(0, (float) $applicationResponse->json('data.assessment.debt_service_ratio'));
    }

    public function test_member_onboarding_rejects_nominees_that_do_not_total_one_hundred_percent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KIN-01', 'branch_name' => 'Kinondoni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/v1/onboarding/groups', ['branch_id' => $branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Test Group', 'meeting_day' => 'Monday', 'location' => 'Kinondoni'])->json('data.id');

        $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id, 'group_id' => $groupId, 'first_name' => 'Invalid', 'last_name' => 'Nominee', 'phone' => '255700000009',
            'nominees' => [['name' => 'Child', 'relationship' => 'Child', 'percentage' => 80]],
        ])->assertUnprocessable()->assertJsonValidationErrors('nominees');
    }
}
