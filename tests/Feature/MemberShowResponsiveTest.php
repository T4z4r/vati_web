<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberShowResponsiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'MR-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'MR-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'MR-B', 'branch_name' => 'Responsive Branch']);

        $this->admin = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Hadi Supervisor']);
        $this->admin->assignRole('super_admin');

        $this->member = Member::create([
            'membership_number' => 'MR-M1',
            'branch_id' => $branch->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255722222222',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_profile_hero_exposes_the_responsive_class_hooks(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.show', $this->member))
            ->assertOk()
            ->assertSee('member-hero', false)
            ->assertSee('member-hero-photo', false)
            ->assertSee('member-hero-initials', false)
            ->assertSee('member-hero-meta', false);
    }

    public function test_the_profile_hero_no_longer_hard_codes_the_photo_size_inline(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.show', $this->member))
            ->assertOk()
            ->assertDontSee('width:132px', false)
            ->assertDontSee('height:132px', false);
    }

    public function test_the_member_page_renders_the_profile_detail_table(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.members.show', $this->member))
            ->assertOk()
            ->assertSee('detail-table', false)
            ->assertSee('Jina la Mwanachama', false);
    }

    public function test_the_stylesheet_keeps_the_responsive_rules_the_profile_depends_on(): void
    {
        $css = (string) file_get_contents(public_path('css/vati.css'));

        // Hero must be able to stack instead of pinning a 132px photo beside the text.
        $this->assertStringContainsString('.member-hero {', $css);
        $this->assertMatchesRegularExpression('/\.member-hero\s*\{[^}]*flex-direction:\s*column/', $css);

        // The fixed 300px label column must be relaxed on small screens.
        $this->assertMatchesRegularExpression('/\.detail-table th\s*\{[^}]*width:\s*38%/', $css);

        // Action rows must wrap so long labels cannot push the page sideways.
        $this->assertDoesNotMatchRegularExpression('/\.head-actions\s*\{[^}]*flex-wrap:\s*nowrap/', $css);
    }
}
