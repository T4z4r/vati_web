<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\DataPurgeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemDataTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MemberGroup $group;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'G-001', 'group_name' => 'Upendo']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');
    }

    private function member(string $number = 'M-1'): Member
    {
        return Member::create([
            'membership_number' => $number,
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255710000001',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_tables_returns_curated_allowlist_with_counts(): void
    {
        $this->member();

        $tables = array_column(app(DataPurgeService::class)->tables(), 'table');
        $counts = app(DataPurgeService::class)->tables();

        $this->assertContains('members', $tables);
        $this->assertContains('loan_applications', $tables);
        $this->assertContains('activity_log', $tables);
        $this->assertNotContains('users', $tables);
        $this->assertNotContains('roles', $tables);
        $this->assertNotContains('number_sequences', $tables);
        $this->assertNotContains('system_settings', $tables);
        $membersRow = collect($counts)->firstWhere('table', 'members');
        $this->assertSame(1, $membersRow['count']);
    }

    public function test_force_delete_table_removes_soft_deleted_records_too(): void
    {
        $this->member();
        $softDeleted = Member::create(['membership_number' => 'M-2', 'branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => 'Binti', 'last_name' => 'Juma', 'phone' => '255710000002', 'created_by' => $this->admin->id]);
        $softDeleted->delete();
        $this->assertSame(2, DB::table('members')->count());

        $result = app(DataPurgeService::class)->forceDeleteTable('members', 'DELETE ALL DATA');

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(0, DB::table('members')->count());
    }

    public function test_force_delete_table_requires_confirmation_phrase(): void
    {
        $this->expectException(\DomainException::class);
        app(DataPurgeService::class)->forceDeleteTable('members', 'nope');
    }

    public function test_unlistable_table_is_rejected(): void
    {
        $this->expectExceptionMessage('The selected table is not deletable.');
        app(DataPurgeService::class)->forceDeleteTable('users', 'DELETE ALL DATA');
    }

    public function test_table_preview_requires_super_admin_role(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($user)
            ->get(route('admin.system.data.tables.preview', ['table' => 'members']))
            ->assertForbidden();
    }

    public function test_admin_can_preview_table_and_force_delete(): void
    {
        $this->member();

        $this->actingAs($this->admin)
            ->get(route('admin.system.data.tables.preview', ['table' => 'members']))
            ->assertOk()
            ->assertJsonPath('data.table', 'members')
            ->assertJsonPath('data.label', 'Members')
            ->assertJsonPath('data.count', 1)
            ->assertJsonCount(1, 'data.rows');

        $this->actingAs($this->admin)
            ->post(route('admin.system.data.tables.delete'), [
                'table' => 'members',
                'confirmation_phrase' => 'DELETE ALL DATA',
                'expected_phrase' => 'DELETE ALL DATA',
            ])
            ->assertRedirect(route('admin.system.data'));

        $this->assertSame(0, DB::table('members')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'Force delete executed: members (1 records)']);
    }

    public function test_admin_can_force_delete_selected_records_only(): void
    {
        $first = $this->member('M-1');
        $second = Member::create(['membership_number' => 'M-2', 'branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => 'Binti', 'last_name' => 'Juma', 'phone' => '255710000002', 'created_by' => $this->admin->id]);
        $this->assertSame(2, DB::table('members')->count());

        $this->actingAs($this->admin)
            ->post(route('admin.system.data.tables.delete'), [
                'table' => 'members',
                'confirmation_phrase' => 'DELETE ALL DATA',
                'expected_phrase' => 'DELETE ALL DATA',
                'ids' => [$first->id],
            ])
            ->assertRedirect(route('admin.system.data'))
            ->assertSessionHas('success');

        $this->assertSame(1, DB::table('members')->count());
        $this->assertDatabaseHas('members', ['id' => $second->id]);
        $this->assertDatabaseMissing('members', ['id' => $first->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Force delete executed: members (1 records)']);
    }

    public function test_force_delete_selected_records_ignores_unknown_ids(): void
    {
        $this->member();

        $result = app(DataPurgeService::class)->forceDeleteTable('members', 'DELETE ALL DATA', [999999]);

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, DB::table('members')->count());
    }

    public function test_table_delete_rejects_non_allowlisted_table(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.system.data.tables.delete'), [
                'table' => 'users',
                'confirmation_phrase' => 'DELETE ALL DATA',
                'expected_phrase' => 'DELETE ALL DATA',
            ])
            ->assertSessionHasErrors('table');
    }
}
