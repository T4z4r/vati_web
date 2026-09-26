<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentListEndpointTest extends TestCase
{
    use RefreshDatabase;

    private array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'RPL']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'APL']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'PL-B', 'branch_name' => 'Payments Branch']);
        $otherBranch = Branch::create(['area_id' => $area->id, 'branch_code' => 'PL-B2', 'branch_name' => 'Second Branch']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'PL-G', 'group_name' => 'Payments Group']);
        $otherGroup = MemberGroup::create(['branch_id' => $otherBranch->id, 'group_code' => 'PL-G2', 'group_name' => 'Second Group']);
        $member = Member::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'PL-M1', 'first_name' => 'Peter', 'last_name' => 'Mushi', 'phone' => '255711100001']);
        $otherMember = Member::create(['branch_id' => $otherBranch->id, 'group_id' => $otherGroup->id, 'membership_number' => 'PL-M2', 'first_name' => 'Joy', 'last_name' => 'Ndosi', 'phone' => '255711100002']);
        $product = LoanProduct::create([
            'name' => 'Payments Loan',
            'code' => 'PLL',
            'minimum_amount' => 1000,
            'maximum_amount' => 2000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 12,
            'repayment_frequency' => 'weekly',
        ]);
        $creator = User::factory()->create();
        $application = LoanApplication::create([
            'application_number' => 'PL-A1',
            'member_id' => $member->id,
            'group_id' => $group->id,
            'branch_id' => $branch->id,
            'loan_product_id' => $product->id,
            'requested_amount' => 1000000,
            'recommended_amount' => 1000000,
            'duration_months' => 6,
            'status' => 'disbursed',
            'created_by' => $creator->id,
        ]);
        $loan = Loan::create([
            'loan_number' => 'PL-L1',
            'loan_application_id' => $application->id,
            'member_id' => $member->id,
            'group_id' => $group->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'principal_amount' => 1000000,
            'interest_amount' => 68000,
            'total_repayment' => 1068000,
            'principal_balance' => 1000000,
            'interest_balance' => 68000,
            'total_balance' => 1068000,
            'number_of_installments' => 24,
            'installment_amount' => 44500,
            'status' => 'active',
        ]);
        $otherApplication = LoanApplication::create([
            'application_number' => 'PL-A2',
            'member_id' => $otherMember->id,
            'group_id' => $otherGroup->id,
            'branch_id' => $otherBranch->id,
            'loan_product_id' => $product->id,
            'requested_amount' => 500000,
            'recommended_amount' => 500000,
            'duration_months' => 3,
            'status' => 'disbursed',
            'created_by' => $creator->id,
        ]);
        $otherLoan = Loan::create([
            'loan_number' => 'PL-L2',
            'loan_application_id' => $otherApplication->id,
            'member_id' => $otherMember->id,
            'group_id' => $otherGroup->id,
            'loan_product_id' => $product->id,
            'branch_id' => $otherBranch->id,
            'principal_amount' => 500000,
            'interest_amount' => 34000,
            'total_repayment' => 534000,
            'principal_balance' => 500000,
            'interest_balance' => 34000,
            'total_balance' => 534000,
            'number_of_installments' => 12,
            'installment_amount' => 44500,
            'status' => 'active',
        ]);
        $installment = $loan->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->addWeek()->toDateString(),
            'total_due' => 44500,
        ]);

        $this->fixtures = compact('branch', 'otherBranch', 'group', 'otherGroup', 'member', 'otherMember', 'product', 'loan', 'otherLoan', 'installment', 'creator');
    }

    public function test_endpoint_requires_view_payments_permission(): void
    {
        $payment = $this->repayment('PL-P1', $this->fixtures['member'], $this->fixtures['loan'], $this->fixtures['branch']);

        Sanctum::actingAs(User::factory()->create(['branch_id' => $this->fixtures['branch']->id]));
        $this->getJson(route('payments.index'))->assertForbidden();

        $auditor = User::factory()->create(['branch_id' => $this->fixtures['branch']->id]);
        $auditor->assignRole('auditor');
        Sanctum::actingAs($auditor);
        $this->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id);
    }

    public function test_it_returns_repayments_with_allocations_and_summary(): void
    {
        $first = $this->repayment('PL-P1', $this->fixtures['member'], $this->fixtures['loan'], $this->fixtures['branch'], [
            'amount' => 44500,
            'paid_at' => now()->subDays(3),
            'payment_method' => 'mpesa',
            'reference_number' => 'MPESA-AAA-111',
        ]);
        PaymentAllocation::create(['payment_id' => $first->id, 'loan_installment_id' => $this->fixtures['installment']->id, 'principal_amount' => 35000, 'interest_amount' => 9500, 'penalty_amount' => 0]);
        $second = $this->repayment('PL-P2', $this->fixtures['member'], $this->fixtures['loan'], $this->fixtures['branch'], [
            'amount' => 20000,
            'paid_at' => now()->subDay(),
            'payment_method' => 'cash',
        ]);
        PaymentAllocation::create(['payment_id' => $second->id, 'loan_installment_id' => null, 'principal_amount' => 20000, 'interest_amount' => 0, 'penalty_amount' => 0]);

        $auditor = User::factory()->create(['branch_id' => $this->fixtures['branch']->id]);
        $auditor->assignRole('auditor');
        Sanctum::actingAs($auditor);

        $this->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.payment_number', 'PL-P1')
            ->assertJsonPath('data.1.payment_method', 'mpesa')
            ->assertJsonPath('data.1.status', 'posted')
            ->assertJsonPath('data.1.member.membership_number', 'PL-M1')
            ->assertJsonPath('data.1.member.full_name', 'Peter Mushi')
            ->assertJsonPath('data.1.loan.loan_number', 'PL-L1')
            ->assertJsonPath('data.1.loan.group_id', $this->fixtures['group']->id)
            ->assertJsonPath('data.1.branch.id', $this->fixtures['branch']->id)
            ->assertJsonPath('data.1.allocations.0.installment_number', 1)
            ->assertJsonPath('data.1.allocations.0.principal_amount', '35000.00')
            ->assertJsonPath('data.1.allocations.0.total_amount', '44500.00')
            ->assertJsonPath('data.1.allocation_totals.principal', '35000.00')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('summary.count', 2)
            ->assertJsonPath('summary.total_amount', '64500.00')
            ->assertJsonPath('summary.posted_amount', '64500.00')
            ->assertJsonPath('summary.principal', '55000.00')
            ->assertJsonPath('summary.interest', '9500.00');
    }

    public function test_it_filters_repayments(): void
    {
        $cash = $this->repayment('PL-P1', $this->fixtures['member'], $this->fixtures['loan'], $this->fixtures['branch'], ['amount' => 50000, 'paid_at' => now()->subDays(10), 'payment_method' => 'cash', 'reference_number' => 'CASH-1', 'external_reference' => 'EXT-1']);
        $mpesa = $this->repayment('PL-P2', $this->fixtures['member'], $this->fixtures['loan'], $this->fixtures['branch'], ['amount' => 120000, 'paid_at' => now()->subDay(), 'payment_method' => 'mpesa', 'reference_number' => 'MPESA-2', 'collected_by' => $this->fixtures['creator']->id]);
        $reversed = $this->repayment('PL-P3', $this->fixtures['otherMember'], $this->fixtures['otherLoan'], $this->fixtures['otherBranch'], ['amount' => 8000, 'paid_at' => now()->subDays(2), 'status' => 'reversed']);

        $headOffice = User::factory()->create();
        $headOffice->assignRole('head_office_admin');
        Sanctum::actingAs($headOffice);

        $this->getJson(route('payments.index', ['payment_method' => 'mpesa']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mpesa->id)
            ->assertJsonPath('summary.total_amount', '120000.00');

        $this->getJson(route('payments.index', ['status' => 'reversed']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reversed->id);

        $this->getJson(route('payments.index', ['date_from' => now()->subDays(3)->toDateString(), 'date_to' => now()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(route('payments.index', ['min_amount' => 100000, 'max_amount' => 200000]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mpesa->id);

        $this->getJson(route('payments.index', ['member_id' => $this->fixtures['otherMember']->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reversed->id);

        $this->getJson(route('payments.index', ['loan_id' => $this->fixtures['loan']->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(route('payments.index', ['group_id' => $this->fixtures['otherGroup']->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reversed->id);

        $this->getJson(route('payments.index', ['loan_product_id' => $this->fixtures['product']->id, 'branch_id' => $this->fixtures['otherBranch']->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reversed->id);

        $this->getJson(route('payments.index', ['collected_by' => $this->fixtures['creator']->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mpesa->id);

        $this->getJson(route('payments.index', ['search' => 'CASH-1']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cash->id);

        $this->getJson(route('payments.index', ['search' => 'Mushi']))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(route('payments.index', ['search' => 'PL-L2']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reversed->id);

        $this->getJson(route('payments.index', ['sort' => 'amount', 'direction' => 'asc', 'per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $reversed->id)
            ->assertJsonPath('data.1.id', $cash->id)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_it_rejects_invalid_filters(): void
    {
        $auditor = User::factory()->create(['branch_id' => $this->fixtures['branch']->id]);
        $auditor->assignRole('auditor');
        Sanctum::actingAs($auditor);

        $this->getJson(route('payments.index', ['status' => 'unknown']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->getJson(route('payments.index', ['payment_method' => 'gold']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->getJson(route('payments.index', ['max_amount' => 10, 'min_amount' => 100]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('max_amount');

        $this->getJson(route('payments.index', ['date_to' => now()->subWeek()->toDateString(), 'date_from' => now()->toDateString()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    public function test_it_scopes_repayments_to_the_users_branch_and_assigned_groups(): void
    {
        $own = $this->repayment('PL-P1', $this->fixtures['member'], $this->fixtures['loan'], $this->fixtures['branch'], ['amount' => 10000]);
        $foreign = $this->repayment('PL-P2', $this->fixtures['otherMember'], $this->fixtures['otherLoan'], $this->fixtures['otherBranch'], ['amount' => 20000]);

        $branchManager = User::factory()->create(['branch_id' => $this->fixtures['branch']->id]);
        $branchManager->assignRole('branch_manager');
        Sanctum::actingAs($branchManager);
        $this->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('summary.count', 1);

        $officer = User::factory()->create(['branch_id' => $this->fixtures['branch']->id]);
        $officer->assignRole('loan_officer');
        $this->fixtures['group']->update(['loan_officer_id' => $officer->id]);
        Sanctum::actingAs($officer);
        $this->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $headOffice = User::factory()->create();
        $headOffice->assignRole('head_office_admin');
        Sanctum::actingAs($headOffice);
        $this->getJson(route('payments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total_amount', '30000.00');

        $this->assertNotNull($foreign);
    }

    private function repayment(string $number, Member $member, Loan $loan, Branch $branch, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'payment_number' => $number,
            'member_id' => $member->id,
            'loan_id' => $loan->id,
            'branch_id' => $branch->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ], $overrides));
    }
}
