<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TableActionIconsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MemberGroup $group;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'ICO-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'ICO-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'ICO-B', 'branch_name' => 'Icons Branch']);
        $this->group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'ICO-G', 'group_name' => 'Icons Group']);

        $this->admin = User::factory()->create(['branch_id' => $branch->id]);
        $this->admin->assignRole('super_admin');

        $this->member = Member::create([
            'branch_id' => $branch->id,
            'group_id' => $this->group->id,
            'membership_number' => 'ICO-M001',
            'first_name' => 'Icon',
            'last_name' => 'Member',
            'phone' => '255730000001',
            'created_by' => $this->admin->id,
        ]);

        $product = LoanProduct::create(['name' => 'Icons Product', 'code' => 'ICO-P', 'interest_method' => 'flat', 'minimum_amount' => 1000, 'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 24, 'annual_interest_rate' => 10]);

        $application = LoanApplication::create([
            'application_number' => 'ICO-APP-001',
            'member_id' => $this->member->id,
            'loan_product_id' => $product->id,
            'group_id' => $this->group->id,
            'branch_id' => $branch->id,
            'requested_amount' => 100000,
            'duration_months' => 6,
            'status' => ApplicationStatus::DRAFT,
            'created_by' => $this->admin->id,
        ]);

        Loan::create([
            'loan_number' => 'ICO-L-001',
            'loan_application_id' => $application->id,
            'member_id' => $this->member->id,
            'group_id' => $this->group->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'principal_amount' => 100000,
            'interest_amount' => 10000,
            'total_repayment' => 110000,
            'principal_balance' => 100000,
            'interest_balance' => 10000,
            'total_balance' => 110000,
            'number_of_installments' => 6,
            'installment_amount' => 18333,
            'status' => LoanStatus::ACTIVE,
        ]);
    }

    public static function listPages(): array
    {
        return [
            'members' => ['admin.members.index'],
            'users' => ['admin.users.index'],
            'groups' => ['admin.groups.index'],
            'loans' => ['admin.loans.index'],
            'loan applications' => ['admin.loan-applications.index'],
        ];
    }

    #[DataProvider('listPages')]
    public function test_list_pages_render_icons_instead_of_words_for_row_actions(string $routeName): void
    {
        $response = $this->actingAs($this->admin)->get(route($routeName))->assertOk();

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/class="table-actions"/', $content);
        $this->assertMatchesRegularExpression('/class="ph ph-eye"\s+aria-hidden="true"/', $content);

        preg_match('/<div class="table-actions">.*?<\/div>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'No table actions markup found on '.$routeName);

        $actions = $matches[0];
        $this->assertSame('', trim(preg_replace('/\s+/', ' ', strip_tags($actions))), 'Row actions still render visible words on '.$routeName);
        $this->assertMatchesRegularExpression('/aria-label="(View|Edit|Delete)"/', $actions);
    }

    public function test_members_page_exposes_eye_pencil_and_trash_actions_with_accessible_labels(): void
    {
        $content = $this->actingAs($this->admin)->get(route('admin.members.index'))->assertOk()->getContent();

        preg_match('/<div class="table-actions">.*?<\/div>/s', $content, $matches);
        $actions = $matches[0];

        $this->assertStringContainsString('class="ph ph-eye"', $actions);
        $this->assertStringContainsString('class="ph ph-pencil-simple"', $actions);
        $this->assertStringContainsString('class="ph ph-trash"', $actions);
        $this->assertStringContainsString('aria-label="View"', $actions);
        $this->assertStringContainsString('aria-label="Edit"', $actions);
        $this->assertStringContainsString('aria-label="Delete"', $actions);
        $this->assertStringContainsString('title="View"', $actions);
        $this->assertStringContainsString('data-confirm=', $actions);
        $this->assertStringContainsString('data-force-text=', $actions);
        $this->assertStringContainsString('data-trash-text=', $actions);
    }
}
