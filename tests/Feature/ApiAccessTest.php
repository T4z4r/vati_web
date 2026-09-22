<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\PortfolioAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_show_returns_loan_applications_of_group_members(): void
    {
        $region = Region::create(['name' => 'Test Region']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Test Area']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'BR1', 'branch_name' => 'Branch']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'G1', 'group_name' => 'Group']);
        $product = LoanProduct::create(['name' => 'Test Loan', 'code' => 'TEST', 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
        $member = Member::create(['membership_number' => 'M1', 'branch_id' => $branch->id, 'group_id' => $group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);
        $application = LoanApplication::create(['application_number' => 'APP-1', 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'submitted', 'created_by' => $user->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('data.applications.0.id', $application->id)
            ->assertJsonPath('data.applications.0.application_number', 'APP-1')
            ->assertJsonPath('data.applications.0.member.id', $member->id);
    }

    public function test_group_show_applications_exclude_members_of_other_groups(): void
    {
        $region = Region::create(['name' => 'Test Region']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Test Area']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'BR1', 'branch_name' => 'Branch']);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $groupA = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'GA', 'group_name' => 'Group A']);
        $groupB = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'GB', 'group_name' => 'Group B']);
        $product = LoanProduct::create(['name' => 'Test Loan', 'code' => 'TEST', 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
        $memberA = Member::create(['membership_number' => 'MA', 'branch_id' => $branch->id, 'group_id' => $groupA->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);
        $memberB = Member::create(['membership_number' => 'MB', 'branch_id' => $branch->id, 'group_id' => $groupB->id, 'first_name' => 'Binti', 'last_name' => 'Juma', 'phone' => '255710000002', 'created_by' => $user->id]);
        LoanApplication::create(['application_number' => 'APP-A', 'member_id' => $memberA->id, 'loan_product_id' => $product->id, 'group_id' => $groupA->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'submitted', 'created_by' => $user->id]);
        LoanApplication::create(['application_number' => 'APP-B', 'member_id' => $memberB->id, 'loan_product_id' => $product->id, 'group_id' => $groupB->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'submitted', 'created_by' => $user->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/groups/{$groupA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.applications')
            ->assertJsonPath('data.applications.0.application_number', 'APP-A');

        $this->getJson("/api/v1/groups/{$groupB->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.applications')
            ->assertJsonPath('data.applications.0.application_number', 'APP-B');
    }

    private function baseData(): array
    {
        $region = Region::create(['name' => 'Test Region']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Test Area']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'BR1', 'branch_name' => 'Branch']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'G1', 'group_name' => 'Group']);
        $product = LoanProduct::create(['name' => 'Test Loan', 'code' => 'TEST', 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        Sanctum::actingAs($user);

        return compact('branch', 'group', 'product', 'user');
    }

    public function test_member_without_loans_or_applications_can_be_deleted(): void
    {
        ['branch' => $branch, 'group' => $group, 'user' => $user] = $this->baseData();
        $member = Member::create(['membership_number' => 'M1', 'branch_id' => $branch->id, 'group_id' => $group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);

        $this->postJson("/api/v1/members/{$member->id}/delete")->assertNoContent();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_member_with_any_loan_application_cannot_be_deleted(): void
    {
        ['branch' => $branch, 'group' => $group, 'product' => $product, 'user' => $user] = $this->baseData();
        $member = Member::create(['membership_number' => 'M1', 'branch_id' => $branch->id, 'group_id' => $group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);
        LoanApplication::create(['application_number' => 'APP-1', 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'draft', 'created_by' => $user->id]);

        $this->postJson("/api/v1/members/{$member->id}/delete")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This member has loans or loan applications and cannot be deleted.');
        $this->assertNotSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_member_with_a_loan_cannot_be_deleted(): void
    {
        ['branch' => $branch, 'group' => $group, 'product' => $product, 'user' => $user] = $this->baseData();
        $member = Member::create(['membership_number' => 'M1', 'branch_id' => $branch->id, 'group_id' => $group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);
        $application = LoanApplication::create(['application_number' => 'APP-1', 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'draft', 'created_by' => $user->id]);
        Loan::create(['loan_number' => 'L-1', 'loan_application_id' => $application->id, 'member_id' => $member->id, 'group_id' => $group->id, 'loan_product_id' => $product->id, 'branch_id' => $branch->id, 'principal_amount' => 1000, 'interest_amount' => 200, 'total_repayment' => 1200, 'principal_balance' => 1000, 'interest_balance' => 200, 'total_balance' => 1200, 'number_of_installments' => 1, 'installment_amount' => 1200, 'status' => 'pending_disbursement']);

        $this->postJson("/api/v1/members/{$member->id}/delete")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This member has loans or loan applications and cannot be deleted.');
        $this->assertNotSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_delete_loan_application_allowed_before_recommendation(): void
    {
        ['branch' => $branch, 'group' => $group, 'product' => $product, 'user' => $user] = $this->baseData();
        $member = Member::create(['membership_number' => 'M1', 'branch_id' => $branch->id, 'group_id' => $group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);
        $application = LoanApplication::create(['application_number' => 'APP-1', 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'returned', 'created_by' => $user->id]);

        $this->postJson("/api/v1/loan-applications/{$application->id}/delete")->assertNoContent();
        $this->assertSoftDeleted('loan_applications', ['id' => $application->id]);
    }

    public function test_delete_loan_application_blocked_for_recommended_or_approved(): void
    {
        ['branch' => $branch, 'group' => $group, 'product' => $product, 'user' => $user] = $this->baseData();
        $member = Member::create(['membership_number' => 'M1', 'branch_id' => $branch->id, 'group_id' => $group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255710000001', 'created_by' => $user->id]);
        foreach (['recommended', 'approved', 'disbursement_pending', 'disbursed'] as $status) {
            $application = LoanApplication::create(['application_number' => 'APP-'.$status, 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $group->id, 'branch_id' => $branch->id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => $status, 'created_by' => $user->id]);

            $this->postJson("/api/v1/loan-applications/{$application->id}/delete")
                ->assertStatus(409)
                ->assertJsonPath('message', 'Only applications that are not recommended can be deleted.');
            $this->assertNotSoftDeleted('loan_applications', ['id' => $application->id]);
        }
    }

    public function test_api_access_requires_authentication(): void
    {
        $this->getJson('/api/v1/groups')->assertUnauthorized();
        $this->getJson('/api/v1/system/settings')->assertUnauthorized();
    }

    public function test_user_without_roles_can_access_and_update_other_branch_data(): void
    {
        $region = Region::create(['name' => 'Test Region']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Test Area']);
        $home = Branch::create(['area_id' => $area->id, 'branch_code' => 'HOME', 'branch_name' => 'Home']);
        $other = Branch::create(['area_id' => $area->id, 'branch_code' => 'OTHER', 'branch_name' => 'Other']);
        $user = User::factory()->create(['branch_id' => $home->id]);
        $group = MemberGroup::create(['branch_id' => $other->id, 'group_code' => 'OTHER-G1', 'group_name' => 'Other Group']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/groups')->assertOk()->assertJsonFragment(['id' => $group->id]);
        $this->getJson("/api/v1/groups/{$group->id}")->assertOk();
        $this->getJson("/api/v1/groups/{$group->id}/dashboard")->assertOk();
        $this->putJson("/api/v1/groups/{$group->id}", ['group_name' => 'Updated'])->assertOk();
        $this->assertDatabaseHas('member_groups', ['id' => $group->id, 'group_name' => 'Updated']);
        $this->postJson("/api/v1/groups/{$group->id}", ['group_name' => 'Updated via POST'])->assertOk();
        $this->assertDatabaseHas('member_groups', ['id' => $group->id, 'group_name' => 'Updated via POST']);
        $this->getJson('/api/v1/portfolio/summary?branch_id='.$other->id)->assertOk();
        $this->getJson('/api/v1/dashboard?branch_id='.$other->id)->assertOk()->assertJsonStructure(['data' => ['management']]);
        $this->getJson('/api/v1/system/settings')->assertOk();

        // Shared services still restrict branch access outside API requests.
        $this->app->instance('request', Request::create('/admin/dashboard'));
        $this->assertSame($home->id, app(PortfolioAnalyticsService::class)->authorizedBranch($user, null));
    }
}
