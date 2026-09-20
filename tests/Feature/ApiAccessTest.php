<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\PortfolioAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

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
        $this->getJson('/api/v1/portfolio/summary?branch_id='.$other->id)->assertOk();
        $this->getJson('/api/v1/dashboard?branch_id='.$other->id)->assertOk()->assertJsonStructure(['data' => ['management']]);
        $this->getJson('/api/v1/system/settings')->assertOk();

        // Shared services still restrict branch access outside API requests.
        $this->app->instance('request', \Illuminate\Http\Request::create('/admin/dashboard'));
        $this->assertSame($home->id, app(PortfolioAnalyticsService::class)->authorizedBranch($user, null));
    }
}
