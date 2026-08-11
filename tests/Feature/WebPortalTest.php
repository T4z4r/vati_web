<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private MemberGroup $group;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $region = Region::create(['name' => 'Dar es Salaam', 'code' => 'DSM']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Kinondoni', 'code' => 'KIN']);
        $this->branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'DSM-001', 'branch_name' => 'Kinondoni']);
        $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->admin->assignRole('super_admin');
        $this->group = MemberGroup::create(['branch_id' => $this->branch->id, 'group_code' => 'KIN-G01', 'group_name' => 'Kinondoni Group']);
        $this->product = LoanProduct::create(['name' => 'Weekly Loan', 'code' => 'WEEKLY', 'minimum_amount' => 100000, 'maximum_amount' => 5000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'interest_method' => 'flat', 'repayment_frequency' => 'weekly', 'security_percentage' => 10, 'processing_fee_percentage' => 2, 'transaction_fee_percentage' => 1, 'membership_fee' => 10000, 'vat_percentage' => 18, 'required_group_witnesses' => 2]);
    }

    public function test_guest_can_sign_in_and_open_dashboard(): void
    {
        $this->get('/')->assertOk()->assertSee('Sign in to portal')->assertSee('One platform.');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_admin_portal_core_pages_render(): void
    {
        $this->actingAs($this->admin);
        foreach (['/admin', '/admin/organization', '/admin/users', '/admin/groups', '/admin/members', '/admin/loan-products', '/admin/loan-applications', '/admin/loans', '/admin/reports'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_web_member_and_application_creation_workflow(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/members', ['branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255712000001', 'admission_date' => today()->format('Y-m-d')])->assertRedirect();
        $member = Member::firstOrFail();
        $this->assertDatabaseHas('group_memberships', ['member_id' => $member->id, 'group_id' => $this->group->id, 'status' => 'active']);
        $this->get(route('admin.members.show', $member))->assertOk()->assertSeeInOrder(['Asha', 'Musa']);
        $this->get(route('admin.groups.show', $this->group))->assertOk()->assertSee('Kinondoni Group');
        $this->get(route('admin.loan-products.show', $this->product))->assertOk()->assertSee('Weekly Loan');

        $this->post('/admin/loan-applications', ['member_id' => $member->id, 'loan_product_id' => $this->product->id, 'application_type' => 'main', 'requested_amount' => 1000000, 'duration_months' => 6, 'core_business_income' => 500000, 'business_expenses' => 100000, 'household_expenses' => 100000])->assertRedirect();
        $this->assertDatabaseHas('loan_applications', ['member_id' => $member->id, 'group_id' => $this->group->id, 'branch_id' => $this->branch->id, 'status' => 'draft']);
        $application = $member->loanApplications()->firstOrFail();
        $this->get(route('admin.loan-applications.show', $application))->assertOk()->assertSee($application->application_number);
    }

    public function test_branch_staff_cannot_open_head_office_administration(): void
    {
        $officer = User::factory()->create(['branch_id' => $this->branch->id]);
        $officer->assignRole('loan_officer');
        $this->actingAs($officer)->get('/admin/organization')->assertForbidden();
    }

    public function test_complete_credit_workflow_can_be_operated_from_web_portal(): void
    {
        $this->actingAs($this->admin);
        $borrower = $this->member('Borrower', '255713000001');
        $firstWitness = $this->member('Witness One', '255713000002');
        $secondWitness = $this->member('Witness Two', '255713000003');

        $this->post('/admin/loan-applications', ['member_id' => $borrower->id, 'loan_product_id' => $this->product->id, 'application_type' => 'main', 'requested_amount' => 1000000, 'duration_months' => 6])->assertRedirect();
        $application = $borrower->loanApplications()->firstOrFail();
        $this->post(route('admin.loan-applications.submit', $application))->assertRedirect();
        $this->post(route('admin.loan-applications.witnesses.store', $application), ['member_id' => $firstWitness->id])->assertRedirect();
        $this->post(route('admin.loan-applications.witnesses.store', $application), ['member_id' => $secondWitness->id])->assertRedirect();
        $this->post(route('admin.loan-applications.approve', $application), ['remarks' => 'Affordability and group confirmation accepted'])->assertRedirect();

        $loan = $application->refresh()->loan;
        $this->assertNotNull($loan);
        $this->get(route('admin.loans.show', $loan))->assertOk()->assertSee($loan->loan_number);
        $this->post(route('admin.loans.disburse', $loan), ['method' => 'cash', 'disbursed_at' => today()->format('Y-m-d'), 'first_payment_date' => today()->addWeek()->format('Y-m-d')])->assertRedirect();
        $this->assertGreaterThan(0, $loan->refresh()->installments()->count());
        $this->post(route('admin.payments.store', $loan), ['amount' => 100000, 'payment_method' => 'cash'])->assertRedirect();
        $this->assertDatabaseHas('payments', ['loan_id' => $loan->id, 'amount' => 100000, 'status' => 'posted']);
        $this->get(route('admin.loans.show', $loan))->assertOk()->assertSee('Payment history');
    }

    private function member(string $name, string $phone): Member
    {
        [$first,$last] = array_pad(explode(' ', $name, 2), 2, 'Member');
        $member = Member::create(['membership_number' => 'VATI-M-'.now()->year.'-'.str_pad((string) (Member::count() + 1), 6, '0', STR_PAD_LEFT), 'branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'created_by' => $this->admin->id]);
        GroupMembership::create(['member_id' => $member->id, 'group_id' => $this->group->id, 'joined_at' => today(), 'status' => 'active']);

        return $member;
    }
}
