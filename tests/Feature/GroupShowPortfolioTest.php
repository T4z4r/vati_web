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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupShowPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MemberGroup $group;

    private MemberGroup $otherGroup;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'GP-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'GP-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'GP-B', 'branch_name' => 'Portfolio Branch']);

        $this->group = MemberGroup::create([
            'branch_id' => $branch->id,
            'group_code' => 'GP-001',
            'group_name' => 'Umoja Wanavyote',
            'loan_officer_id' => null,
        ]);

        $this->otherGroup = MemberGroup::create([
            'branch_id' => $branch->id,
            'group_code' => 'GP-002',
            'group_name' => 'Kujengeka Group',
            'loan_officer_id' => null,
        ]);

        $this->product = LoanProduct::create(['code' => 'GP-P1', 'name' => 'Main loan']);

        $this->admin = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Hadi Supervisor']);
        $this->admin->assignRole('super_admin');
    }

    private function member(MemberGroup $group, string $code, string $first, string $last, string $phone): Member
    {
        return Member::create([
            'membership_number' => $code,
            'branch_id' => $group->branch_id,
            'group_id' => $group->id,
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    private function application(MemberGroup $group, Member $member, string $number, int $amount, string $status = 'submitted'): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => $number,
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'group_id' => $group->id,
            'branch_id' => $group->branch_id,
            'requested_amount' => $amount,
            'duration_months' => 20,
            'status' => $status,
        ]);
    }

    private function loan(MemberGroup $group, Member $member, LoanApplication $application, string $number, int $principal, int $balance, string $status = 'active'): Loan
    {
        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $member->id,
            'group_id' => $group->id,
            'loan_product_id' => $this->product->id,
            'branch_id' => $group->branch_id,
            'principal_amount' => $principal,
            'total_repayment' => (int) ($principal * 1.2),
            'principal_balance' => $balance,
            'total_balance' => $balance,
            'status' => $status,
        ]);
    }

    public function test_the_group_page_lists_the_groups_loans_with_product_amounts_and_links(): void
    {
        $member = $this->member($this->group, 'GP-M1', 'Asha', 'Musa', '255700111222');
        $active = $this->loan(
            $this->group,
            $member,
            $this->application($this->group, $member, 'GP-APP-1', 500000),
            'GP-LN-1',
            500000,
            400000
        );
        $settled = $this->loan(
            $this->group,
            $member,
            $this->application($this->group, $member, 'GP-APP-2', 100000, 'disbursed'),
            'GP-LN-2',
            100000,
            0,
            'settled'
        );

        $this->actingAs($this->admin)
            ->get(route('admin.groups.show', $this->group->id))
            ->assertOk()
            ->assertSee(__('Loans'))
            ->assertSee(__('Principal'))
            ->assertSee(__('Outstanding'))
            ->assertSee('GP-LN-1')
            ->assertSee('GP-LN-2')
            ->assertSee($this->product->name)
            ->assertSee('TZS '.number_format(500000, 2))
            ->assertSee('TZS '.number_format(400000, 2))
            ->assertSee('settled')
            ->assertSee(route('admin.loans.show', $active), false)
            ->assertSee(route('admin.loans.show', $settled), false)
            ->assertSee(route('admin.members.show', $member), false);
    }

    public function test_the_group_page_lists_the_groups_loan_applications_with_product_and_requested_amount(): void
    {
        $member = $this->member($this->group, 'GP-M1', 'Asha', 'Musa', '255700111222');
        $application = $this->application($this->group, $member, 'GP-APP-1', 750000);

        $this->actingAs($this->admin)
            ->get(route('admin.groups.show', $this->group->id))
            ->assertOk()
            ->assertSee(__('Loan applications'))
            ->assertSee(__('Requested'))
            ->assertSee(__('Product'))
            ->assertSee('GP-APP-1')
            ->assertSee($this->product->name)
            ->assertSee('TZS '.number_format(750000, 2))
            ->assertSee(route('admin.loan-applications.show', $application), false);
    }

    public function test_the_group_page_never_shows_another_groups_loans_or_applications(): void
    {
        $otherMember = $this->member($this->otherGroup, 'GP-M9', 'Neema', 'Juma', '255700999000');
        $otherApplication = $this->application($this->otherGroup, $otherMember, 'GP-APP-99', 900000);
        $this->loan($this->otherGroup, $otherMember, $otherApplication, 'GP-LN-99', 900000, 900000);

        $this->actingAs($this->admin)
            ->get(route('admin.groups.show', $this->group->id))
            ->assertOk()
            ->assertDontSee('GP-LN-99')
            ->assertDontSee('GP-APP-99')
            ->assertDontSee('Neema')
            ->assertDontSee('255700999000')
            ->assertSee(__('No loans recorded for this group yet.'))
            ->assertSee(__('No loan applications for this group yet.'));
    }

    public function test_the_loan_and_application_cards_stack_below_the_members_table_in_the_same_column(): void
    {
        $member = $this->member($this->group, 'GP-M1', 'Asha', 'Musa', '255700111222');
        $this->loan(
            $this->group,
            $member,
            $this->application($this->group, $member, 'GP-APP-1', 500000),
            'GP-LN-1',
            500000,
            400000
        );

        $response = $this->actingAs($this->admin)->get(route('admin.groups.show', $this->group->id));
        $response->assertOk();

        $response->assertSeeInOrder([
            'grid-stack',
            __('Members and loan balances'),
            'GP-LN-1',
            __('Loan applications'),
            'GP-APP-1',
            __('Operating details'),
        ], false);
    }
}
