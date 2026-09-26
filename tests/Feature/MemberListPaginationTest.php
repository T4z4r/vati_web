<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'MP-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'MP-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'MP-B', 'branch_name' => 'Pagination Branch']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'MP-G', 'group_name' => 'Pagination Group']);

        foreach (range(1, 25) as $index) {
            Member::create([
                'branch_id' => $branch->id,
                'group_id' => $group->id,
                'membership_number' => sprintf('MP-M%03d', $index),
                'first_name' => 'Test',
                'last_name' => sprintf('Member%02d', $index),
                'phone' => '25571'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
            ]);
        }

        $this->admin = User::factory()->create(['branch_id' => $branch->id]);
        $this->admin->assignRole('super_admin');
    }

    public function test_members_list_paginates_twenty_per_page_by_default(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index'))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->total() === 25 && $members->count() === 20 && $members->currentPage() === 1 && $members->lastPage() === 2)
            ->assertSee('Showing 1–20')
            ->assertSee('page=2', false);
    }

    public function test_members_list_paginates_the_second_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->count() === 5 && $members->currentPage() === 2)
            ->assertSee('Showing 21–25');
    }

    public function test_members_list_honours_per_page_and_keeps_filters_in_page_links(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['per_page' => 10, 'status' => 'active']))
            ->assertOk()
            ->assertViewHas('members', function ($members) {
                $this->assertSame(25, $members->total());
                $this->assertSame(10, $members->count());
                $this->assertSame(3, $members->lastPage());

                return true;
            })
            ->assertSee('Showing 1–10')
            ->assertSee('per_page=10', false)
            ->assertSee('status=active', false)
            ->assertSee('page=2', false);
    }

    public function test_members_list_falls_back_to_twenty_for_unsupported_per_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['per_page' => 7]))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->count() === 20);
    }

    public function test_members_list_pagination_is_hidden_when_results_fit_one_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.index', ['search' => 'Member01']))
            ->assertOk()
            ->assertDontSee('Showing 1–1 of 1');
    }
}
