<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanRevertTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $region = Region::create(['name' => 'Region', 'code' => 'R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'RV-B', 'branch_name' => 'Revert']);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'RV-G', 'group_name' => 'Revert']);
        $user = User::factory()->create();
        $member = Member::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'RV-M', 'first_name' => 'Reva', 'last_name' => 'Sana', 'phone' => '255711111114']);
        $product = LoanProduct::create([
            'name' => 'Revert Loan',
            'code' => 'RVL',
            'minimum_amount' => 1000,
            'maximum_amount' => 2000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 12,
            'repayment_frequency' => 'weekly',
        ]);

        return compact('branch', 'group', 'member', 'product', 'user');
    }

    private function application(array $fixtures, string $number = 'RV-A-1'): LoanApplication
    {
        return LoanApplication::create([
            'application_number' => $number,
            'member_id' => $fixtures['member']->id,
            'group_id' => $fixtures['group']->id,
            'branch_id' => $fixtures['branch']->id,
            'loan_product_id' => $fixtures['product']->id,
            'requested_amount' => 1000000,
            'recommended_amount' => 1000000,
            'duration_months' => 6,
            'status' => 'approved',
            'created_by' => $fixtures['user']->id,
        ]);
    }

    private function loan(LoanApplication $application, array $fixtures, string $number = 'RV-L-1'): Loan
    {
        return Loan::create([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $fixtures['member']->id,
            'group_id' => $fixtures['group']->id,
            'loan_product_id' => $fixtures['product']->id,
            'branch_id' => $fixtures['branch']->id,
            'principal_amount' => 1000000,
            'interest_amount' => 68000,
            'total_repayment' => 1068000,
            'principal_balance' => 1000000,
            'interest_balance' => 68000,
            'total_balance' => 1068000,
            'number_of_installments' => 24,
            'installment_amount' => 44500,
        ]);
    }

    private function superAdmin(Branch $branch): User
    {
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_super_admin_can_revert_pending_loan_to_approved_application(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures);
        $loan = $this->loan($application, $fixtures);
        $admin = $this->superAdmin($fixtures['branch']);

        $this->actingAs($admin)
            ->from(route('admin.loans.show', $loan))
            ->post(route('admin.loans.revert', $loan))
            ->assertRedirect(route('admin.loan-applications.show', $application))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('loans', ['loan_number' => 'RV-L-1']);
        $this->assertSame('approved', $application->refresh()->status->value);
        $this->assertDatabaseHas('activity_log', ['description' => 'Loan reverted to approved loan application']);
    }

    public function test_revert_deletes_loan_children_for_clean_disbursed_loan(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures);
        $loan = $this->loan($application, $fixtures);
        $application->update(['status' => 'disbursed']);
        $loan->update(['status' => 'active']);
        LoanDisbursement::create(['loan_id' => $loan->id, 'amount' => 873200, 'method' => 'cash', 'status' => 'completed']);
        for ($i = 1; $i <= 24; $i++) {
            $loan->installments()->create([
                'installment_number' => $i,
                'due_date' => now()->addWeeks($i)->toDateString(),
                'total_due' => 44500,
            ]);
        }
        $admin = $this->superAdmin($fixtures['branch']);

        $this->actingAs($admin)
            ->post(route('admin.loans.revert', $loan))
            ->assertRedirect(route('admin.loan-applications.show', $application));

        $this->assertDatabaseMissing('loans', ['loan_number' => 'RV-L-1']);
        $this->assertDatabaseMissing('loan_disbursements', ['loan_id' => $loan->id]);
        $this->assertDatabaseMissing('loan_installments', ['loan_id' => $loan->id]);
        $this->assertSame('approved', $application->refresh()->status->value);
    }

    public function test_revert_is_denied_when_loan_has_payments(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures);
        $loan = $this->loan($application, $fixtures);
        Payment::create([
            'payment_number' => 'RV-PAY-1',
            'member_id' => $fixtures['member']->id,
            'loan_id' => $loan->id,
            'branch_id' => $fixtures['branch']->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);
        $admin = $this->superAdmin($fixtures['branch']);

        $this->actingAs($admin)
            ->from(route('admin.loans.show', $loan))
            ->post(route('admin.loans.revert', $loan))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('loans', ['loan_number' => 'RV-L-1']);
        $this->assertSame('approved', $application->refresh()->status->value);
    }

    public function test_force_revert_deletes_associated_loan_data(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures);
        $loan = $this->loan($application, $fixtures);
        $application->update(['status' => 'disbursed']);
        $loan->update(['status' => 'active']);

        $installment = $loan->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->addWeek()->toDateString(),
            'total_due' => 44500,
        ]);
        $payment = Payment::create([
            'payment_number' => 'RV-PAY-1',
            'member_id' => $fixtures['member']->id,
            'loan_id' => $loan->id,
            'branch_id' => $fixtures['branch']->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);
        DB::table('payment_allocations')->insert([
            'payment_id' => $payment->id,
            'loan_installment_id' => $installment->id,
            'principal_amount' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('loan_disbursements')->insert(['loan_id' => $loan->id, 'amount' => 873200, 'method' => 'cash', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_default_notices')->insert(['loan_id' => $loan->id, 'delivery_method' => 'hand', 'notice_text' => 'Default notice', 'issued_at' => now(), 'expires_at' => now()->addDays(14), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_security_transactions')->insert(['loan_id' => $loan->id, 'transaction_date' => today(), 'security_amount' => 100000, 'balance' => 100000, 'created_at' => now(), 'updated_at' => now()]);

        $accountId = DB::table('member_security_accounts')->insertGetId(['member_id' => $fixtures['member']->id, 'balance' => 125000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('security_transactions')->insert([
            ['transaction_number' => 'RV-SEC-1', 'member_security_account_id' => $accountId, 'loan_id' => null, 'transaction_type' => 'deposit', 'amount' => 25000, 'balance_before' => 0, 'balance_after' => 25000, 'transaction_date' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()],
            ['transaction_number' => 'RV-SEC-2', 'member_security_account_id' => $accountId, 'loan_id' => $loan->id, 'transaction_type' => 'deposit', 'amount' => 100000, 'balance_before' => 25000, 'balance_after' => 125000, 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $admin = $this->superAdmin($fixtures['branch']);

        $this->actingAs($admin)
            ->post(route('admin.loans.revert', $loan), ['_force' => 1])
            ->assertRedirect(route('admin.loan-applications.show', $application))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('loans', ['loan_number' => 'RV-L-1']);
        $this->assertDatabaseMissing('payments', ['loan_id' => $loan->id]);
        $this->assertDatabaseMissing('payment_allocations', ['payment_id' => $payment->id]);
        $this->assertDatabaseMissing('loan_disbursements', ['loan_id' => $loan->id]);
        $this->assertDatabaseMissing('loan_default_notices', ['loan_id' => $loan->id]);
        $this->assertDatabaseMissing('loan_security_transactions', ['loan_id' => $loan->id]);
        $this->assertDatabaseMissing('security_transactions', ['loan_id' => $loan->id]);
        $this->assertDatabaseHas('member_security_accounts', ['id' => $accountId, 'balance' => 25000]);
        $this->assertSame('approved', $application->refresh()->status->value);
    }

    public function test_revert_is_restricted_to_super_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $application = $this->application($fixtures);
        $loan = $this->loan($application, $fixtures);
        $plain = User::factory()->create(['branch_id' => $fixtures['branch']->id]);

        $this->actingAs($plain)
            ->post(route('admin.loans.revert', $loan))
            ->assertForbidden();

        $this->assertDatabaseHas('loans', ['loan_number' => 'RV-L-1']);
    }
}
