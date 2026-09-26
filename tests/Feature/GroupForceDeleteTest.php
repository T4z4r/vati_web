<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupVisit;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Branch, 2: MemberGroup, 3: MemberGroup}
     */
    private function seedWorld(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $branch = Branch::create(['area_id' => $this->area()->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Kinondoni Group']);
        $empty = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G02', 'group_name' => 'Empty Group']);

        return [$admin, $branch, $group, $empty];
    }

    private function area(): Area
    {
        $region = Region::firstOrCreate(['code' => 'DSM'], ['name' => 'Dar es Salaam']);

        return Area::firstOrCreate(['region_id' => $region->id, 'code' => 'KIN'], ['name' => 'Kinondoni']);
    }

    private function member(Branch $branch, MemberGroup $group, int $createdBy, string $suffix = '01'): Member
    {
        return Member::create([
            'branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'DSM-'.strtoupper($group->group_code).'-'.$suffix,
            'first_name' => 'Asha', 'middle_name' => 'Musa', 'last_name' => 'Juma', 'guardian_name' => 'Juma Musa',
            'phone' => '2557123'.mt_rand(10000, 99999), 'gender' => 'Female', 'occupation' => 'Trader',
            'physical_address' => 'Kinondoni', 'admission_date' => today(), 'created_by' => $createdBy,
        ]);
    }

    private function loanFor(MemberGroup $group, Member $member, int $createdBy): Loan
    {
        $product = LoanProduct::create(['name' => 'Group Loan', 'code' => 'GL-'.$group->group_code, 'minimum_amount' => 1000, 'maximum_amount' => 1000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'repayment_frequency' => 'weekly', 'required_group_witnesses' => 0]);
        $application = LoanApplication::create(['application_number' => 'APP-'.$group->group_code, 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $group->id, 'branch_id' => $group->branch_id, 'requested_amount' => 1000, 'duration_months' => 1, 'status' => 'approved', 'created_by' => $createdBy]);

        return Loan::create(['loan_number' => 'L-'.$group->group_code, 'loan_application_id' => $application->id, 'member_id' => $member->id, 'group_id' => $group->id, 'loan_product_id' => $product->id, 'branch_id' => $group->branch_id, 'principal_amount' => 1000, 'interest_amount' => 200, 'total_repayment' => 1200, 'principal_balance' => 1000, 'interest_balance' => 200, 'total_balance' => 1200, 'number_of_installments' => 1, 'installment_amount' => 1200, 'status' => 'active']);
    }

    private function userWithRole(string $role, Branch $branch): User
    {
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_api_deletes_group_without_linked_records(): void
    {
        [, , , $empty] = $this->seedWorld();

        $this->deleteJson("/api/v1/groups/{$empty->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('member_groups', ['id' => $empty->id]);
    }

    public function test_api_loan_officer_can_delete_group_assigned_to_them(): void
    {
        [, $branch] = $this->seedWorld();
        $officer = $this->userWithRole('loan_officer', $branch);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G03', 'group_name' => 'Officer Group', 'loan_officer_id' => $officer->id]);
        Sanctum::actingAs($officer);

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('member_groups', ['id' => $group->id]);
    }

    public function test_api_credit_officer_can_delete_group(): void
    {
        [, $branch, , $empty] = $this->seedWorld();
        Sanctum::actingAs($this->userWithRole('credit_officer', $branch));

        $this->deleteJson("/api/v1/groups/{$empty->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('member_groups', ['id' => $empty->id]);
    }

    public function test_api_forbids_roles_without_group_deletion_permission(): void
    {
        [, $branch] = $this->seedWorld();

        foreach (['cashier', 'finance_officer', 'auditor', 'member'] as $index => $role) {
            $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-R'.$index, 'group_name' => 'Role Group '.$role]);
            Sanctum::actingAs($this->userWithRole($role, $branch));

            $this->deleteJson("/api/v1/groups/{$group->id}")
                ->assertForbidden();

            $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
        }
    }

    public function test_api_forbids_loan_officer_deleting_another_officers_group(): void
    {
        [, $branch, $group] = $this->seedWorld();
        $officer = $this->userWithRole('loan_officer', $branch);
        Sanctum::actingAs($officer);

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_api_refuses_group_with_members(): void
    {
        [$admin, $branch, $group] = $this->seedWorld();
        $this->member($branch, $group, $admin->id);

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This group cannot be deleted because it still has members. Remove or reassign them first.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_api_refuses_group_with_removed_members_and_keeps_their_records(): void
    {
        [$admin, $branch, $group] = $this->seedWorld();
        $member = $this->member($branch, $group, $admin->id);
        $loan = $this->loanFor($group, $member, $admin->id);
        $member->delete();

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This group cannot be deleted because it still has members, loans, loan applications. Remove or reassign them first.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
        $this->assertSoftDeleted('members', ['id' => $member->id]);
        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
    }

    public function test_api_refuses_group_with_visits(): void
    {
        [$admin, , $group] = $this->seedWorld();
        GroupVisit::create(['group_id' => $group->id, 'user_id' => $admin->id, 'visit_date' => today()]);

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This group cannot be deleted because it still has recorded visits. Remove or reassign them first.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_api_refuses_group_with_loan_applications_and_keeps_them(): void
    {
        [$admin, $branch, $group] = $this->seedWorld();
        $member = $this->member($branch, $group, $admin->id);
        $loan = $this->loanFor($group, $member, $admin->id);

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('loans', ['id' => $loan->id]);
        $this->assertDatabaseHas('loan_applications', ['group_id' => $group->id]);
    }

    public function test_api_refuses_group_with_leftover_memberships(): void
    {
        [$admin, $branch, $group] = $this->seedWorld();
        $member = $this->member($branch, $group, $admin->id);
        DB::table('group_memberships')->insert(['member_id' => $member->id, 'group_id' => $group->id, 'joined_at' => today(), 'status' => 'inactive']);
        $member->delete();

        $this->deleteJson("/api/v1/groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This group cannot be deleted because it still has members, membership records. Remove or reassign them first.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('group_memberships', ['group_id' => $group->id]);
    }

    public function test_legacy_post_delete_route_uses_the_same_rules(): void
    {
        [, $branch] = $this->seedWorld();
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G04', 'group_name' => 'Legacy Group']);
        Sanctum::actingAs($this->userWithRole('cashier', $branch));

        $this->postJson("/api/v1/groups/{$group->id}/delete")
            ->assertForbidden();

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_web_deletes_group_without_linked_records(): void
    {
        [$admin, , , $empty] = $this->seedWorld();

        $this->actingAs($admin)
            ->delete(route('admin.groups.destroy', $empty->id))
            ->assertRedirect(route('admin.groups.index'));

        $this->assertDatabaseMissing('member_groups', ['id' => $empty->id]);
    }

    public function test_web_refuses_group_with_members(): void
    {
        [$admin, $branch, $group] = $this->seedWorld();
        $this->member($branch, $group, $admin->id);

        $this->actingAs($admin)
            ->from(route('admin.groups.show', $group->id))
            ->delete(route('admin.groups.destroy', $group->id))
            ->assertRedirect(route('admin.groups.show', $group->id))
            ->assertSessionHas('error', 'This group cannot be deleted because it still has members. Remove or reassign them first.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_web_refuses_group_with_visits(): void
    {
        [$admin, , $group] = $this->seedWorld();
        GroupVisit::create(['group_id' => $group->id, 'user_id' => $admin->id, 'visit_date' => today()]);

        $this->actingAs($admin)
            ->from(route('admin.groups.show', $group->id))
            ->delete(route('admin.groups.destroy', $group->id))
            ->assertSessionHas('error', 'This group cannot be deleted because it still has recorded visits. Remove or reassign them first.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_web_forbids_roles_without_group_deletion_permission(): void
    {
        [, $branch, , $empty] = $this->seedWorld();

        $this->actingAs($this->userWithRole('cashier', $branch))
            ->delete(route('admin.groups.destroy', $empty->id))
            ->assertForbidden();

        $this->assertDatabaseHas('member_groups', ['id' => $empty->id]);
    }
}
