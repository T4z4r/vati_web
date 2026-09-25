<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_accepts_asset_matrix_payload(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM-MATRIX']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kigamboni', 'code' => 'KGM-MATRIX']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KGM-M01', 'branch_name' => 'Kigamboni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $groupId = $this->postJson('/api/v1/onboarding/groups', [
            'branch_id' => $branch->id,
            'group_code' => 'KGM-GMATRIX',
            'group_name' => 'Matrix Group',
            'meeting_day' => 'Monday',
            'meeting_time' => '10:00',
            'location' => 'Kigamboni Market',
        ])->assertCreated()->json('data.id');

        $memberId = $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id,
            'group_id' => $groupId,
            'first_name' => 'Matrix',
            'last_name' => 'Onboarding',
            'phone' => '255712000099',
            'asset_matrix' => [
                ['name' => 'Sofa', 'category' => 'Household', 'quantity' => 1, 'estimated_value' => 500000],
                ['name' => 'Television', 'category' => 'Household', 'quantity' => 2, 'estimated_value' => 700000, 'description' => 'Working'],
                ['name' => '', 'category' => '', 'quantity' => '', 'estimated_value' => '', 'description' => ''],
            ],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseCount('member_assets', 2);
        $this->assertDatabaseHas('asset_types', ['name' => 'Sofa', 'category' => 'Household']);
        $this->assertDatabaseHas('member_assets', ['member_id' => $memberId, 'quantity' => 2, 'estimated_value' => 700000]);
    }

    public function test_member_can_be_registered_without_details_and_completed_later(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM-OPTIONAL']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kigamboni', 'code' => 'KGM-OPTIONAL']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KGM-OPTIONAL', 'branch_name' => 'Kigamboni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $memberId = $this->postJson('/api/v1/onboarding/members')
            ->assertCreated()
            ->assertJsonPath('data.first_name', null)
            ->assertJsonPath('data.last_name', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.branch', null)
            ->assertJsonPath('data.group', null)
            ->json('data.id');

        $this->assertDatabaseHas('members', [
            'id' => $memberId,
            'branch_id' => null,
            'group_id' => null,
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
        ]);
        $this->assertDatabaseCount('group_memberships', 0);

        $groupId = $this->postJson('/api/v1/onboarding/groups', [
            'branch_id' => $branch->id,
            'group_code' => 'KGM-OPTIONAL-G',
            'group_name' => 'Optional Details Group',
            'meeting_day' => 'Monday',
            'location' => 'Kigamboni',
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/members/'.$memberId, [
            'branch_id' => $branch->id,
            'group_id' => $groupId,
            'first_name' => 'Completed',
            'last_name' => 'Member',
            'phone' => '255712000098',
        ])->assertOk()
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.group.id', $groupId)
            ->assertJsonPath('data.first_name', 'Completed');

        $this->assertDatabaseHas('members', [
            'id' => $memberId,
            'branch_id' => $branch->id,
            'group_id' => $groupId,
            'first_name' => 'Completed',
            'last_name' => 'Member',
            'phone' => '255712000098',
        ]);
        $this->assertDatabaseHas('group_memberships', [
            'member_id' => $memberId,
            'group_id' => $groupId,
            'status' => 'active',
        ]);

        $this->patchJson('/api/v1/members/'.$memberId, [
            'middle_name' => 'Preserved',
        ])->assertOk()
            ->assertJsonPath('data.middle_name', 'Preserved')
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.group.id', $groupId);

        $this->patchJson('/api/v1/members/'.$memberId, [
            'branch_id' => null,
            'group_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.branch', null)
            ->assertJsonPath('data.group', null);

        $this->assertDatabaseHas('members', [
            'id' => $memberId,
            'branch_id' => null,
            'group_id' => null,
        ]);
        $this->assertDatabaseHas('group_memberships', [
            'member_id' => $memberId,
            'group_id' => $groupId,
            'status' => 'inactive',
        ]);
    }

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
            'asset_matrix' => [
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
            ->assertJsonPath('data.requirements.approval.complete_guarantors_required', 0);

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

    public function test_onboarding_group_is_auto_assigned_to_the_creating_loan_officer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KIN-01', 'branch_name' => 'Kinondoni']);
        $officer = User::factory()->create(['branch_id' => $branch->id]);
        $officer->assignRole('loan_officer');
        Sanctum::actingAs($officer);

        $this->postJson('/api/v1/onboarding/groups', [
            'branch_id' => $branch->id,
            'group_name' => 'Officer Group',
            'meeting_day' => 'Monday',
            'location' => 'Kinondoni',
            'loan_officer_id' => $officer->id,
        ])->assertCreated()
            ->assertJsonPath('data.loan_officer.id', $officer->id)
            ->assertJsonPath('data.loan_officer_id', $officer->id);
    }

    public function test_loan_application_creation_persists_provided_created_at(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KIN-03', 'branch_name' => 'Kinondoni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/v1/onboarding/groups', [
            'branch_id' => $branch->id,
            'group_code' => 'KIN-G03',
            'group_name' => 'CreatedAt Group',
            'meeting_day' => 'Monday',
            'location' => 'Kinondoni',
        ])->json('data.id');

        $memberId = $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id, 'group_id' => $groupId, 'first_name' => 'Asha', 'last_name' => 'Musa',
            'phone' => '255700000020',
        ])->json('data.id');

        $product = LoanProduct::create([
            'name' => 'Created At Loan', 'code' => 'CREATED', 'minimum_amount' => 100000,
            'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12,
            'repayment_frequency' => 'monthly', 'status' => true,
        ]);

        $response = $this->postJson('/api/v1/loan-applications', [
            'member_id' => $memberId,
            'loan_product_id' => $product->id,
            'application_type' => 'main',
            'requested_amount' => 600000,
            'duration_months' => 6,
            'loan_purpose' => 'Restock inventory',
            'created_at' => '2024-05-01',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'application_number', 'created_at']]);

        $returnedCreatedAt = \Illuminate\Support\Carbon::parse($response->json('data.created_at'))
            ->setTimezone(config('app.timezone'))->toDateString();
        $this->assertSame('2024-05-01', $returnedCreatedAt);

        $applicationId = $response->json('data.id');
        $createdAt = DB::table('loan_applications')->where('id', $applicationId)->value('created_at');
        $this->assertStringStartsWith('2024-05-01', (string) $createdAt);
    }

    public function test_member_onboarding_allows_missing_and_duplicate_national_ids(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'KIN-02', 'branch_name' => 'Kinondoni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);
        $groupId = $this->postJson('/api/v1/onboarding/groups', [
            'branch_id' => $branch->id,
            'group_code' => 'KIN-G02',
            'group_name' => 'Dup Group',
            'meeting_day' => 'Monday',
            'location' => 'Kinondoni',
        ])->json('data.id');

        $sharedId = '19900101-12345-00001-00';

        $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id, 'group_id' => $groupId, 'first_name' => 'Asha', 'last_name' => 'Musa',
            'phone' => '255700000010', 'national_id' => $sharedId,
        ])->assertCreated();

        $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id, 'group_id' => $groupId, 'first_name' => 'Binti', 'last_name' => 'Juma',
            'phone' => '255700000011', 'national_id' => $sharedId,
        ])->assertCreated();

        $this->postJson('/api/v1/onboarding/members', [
            'branch_id' => $branch->id, 'group_id' => $groupId, 'first_name' => 'Neema', 'last_name' => 'Pili',
            'phone' => '255700000012',
        ])->assertCreated()->assertJsonPath('data.national_id', null);
    }
}
