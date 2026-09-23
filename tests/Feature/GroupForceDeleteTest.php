<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupVisit;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Kinondoni Group']);
        $empty = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'KIN-G02', 'group_name' => 'Empty Group']);

        return [$admin, $branch, $group, $empty];
    }

    private function member(Branch $branch, MemberGroup $group, int $createdBy): Member
    {
        return Member::create([
            'branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'DSM-'.strtoupper($group->group_code).'-01',
            'first_name' => 'Asha', 'middle_name' => 'Musa', 'last_name' => 'Juma', 'guardian_name' => 'Juma Musa',
            'phone' => '2557123'.mt_rand(10000, 99999), 'gender' => 'Female', 'occupation' => 'Trader',
            'physical_address' => 'Kinondoni', 'admission_date' => today(), 'created_by' => $createdBy,
        ]);
    }

    public function test_api_force_deletes_group_without_members_or_visits(): void
    {
        [, , , $empty] = $this->seedWorld();

        $this->postJson("/api/v1/groups/{$empty->id}/delete")
            ->assertNoContent();

        $this->assertDatabaseMissing('member_groups', ['id' => $empty->id]);
    }

    public function test_api_refuses_group_with_members(): void
    {
        [$admin, $branch, $group] = $this->seedWorld();
        $this->member($branch, $group, $admin->id);

        $this->postJson("/api/v1/groups/{$group->id}/delete")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This group has members or recorded visits and cannot be deleted.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_api_refuses_group_with_visits(): void
    {
        [$admin, , $group] = $this->seedWorld();
        GroupVisit::create(['group_id' => $group->id, 'user_id' => $admin->id, 'visit_date' => today()]);

        $this->postJson("/api/v1/groups/{$group->id}/delete")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This group has members or recorded visits and cannot be deleted.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_web_force_deletes_group_without_members_or_visits(): void
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
            ->assertSessionHas('error', 'This group has members or recorded visits and cannot be deleted.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }

    public function test_web_refuses_group_with_visits(): void
    {
        [$admin, , $group] = $this->seedWorld();
        GroupVisit::create(['group_id' => $group->id, 'user_id' => $admin->id, 'visit_date' => today()]);

        $this->actingAs($admin)
            ->from(route('admin.groups.show', $group->id))
            ->delete(route('admin.groups.destroy', $group->id))
            ->assertSessionHas('error', 'This group has members or recorded visits and cannot be deleted.');

        $this->assertDatabaseHas('member_groups', ['id' => $group->id]);
    }
}