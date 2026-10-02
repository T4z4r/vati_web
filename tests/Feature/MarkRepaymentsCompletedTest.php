<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkRepaymentsCompletedTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(string $code = 'MRC'): array
    {
        $region = Region::create(['name' => 'Region', 'code' => 'R-'.$code]);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'A-'.$code]);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => $code.'-B', 'branch_name' => 'Branch '.$code]);
        $group = MemberGroup::create(['branch_id' => $branch->id, 'group_code' => $code.'-G', 'group_name' => 'Group '.$code]);
        $product = LoanProduct::create([
            'name' => 'Bulk Loan',
            'code' => $code,
            'minimum_amount' => 1000,
            'maximum_amount' => 2000000,
            'minimum_duration_months' => 1,
            'maximum_duration_months' => 12,
            'repayment_frequency' => 'monthly',
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        return compact('branch', 'group', 'product', 'user');
    }

    private function loan(array $fixtures, string $number, array $overrides = []): Loan
    {
        $member = Member::create([
            'branch_id' => $fixtures['branch']->id,
            'group_id' => $fixtures['group']->id,
            'membership_number' => $number.'-M',
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255'.random_int(700000000, 799999999),
        ]);
        $application = LoanApplication::create([
            'application_number' => $number.'-A',
            'member_id' => $member->id,
            'group_id' => $fixtures['group']->id,
            'branch_id' => $fixtures['branch']->id,
            'loan_product_id' => $fixtures['product']->id,
            'requested_amount' => 100000,
            'duration_months' => 2,
            'status' => 'approved',
            'created_by' => $fixtures['user']->id,
        ]);

        return Loan::create(array_merge([
            'loan_number' => $number,
            'loan_application_id' => $application->id,
            'member_id' => $member->id,
            'group_id' => $fixtures['group']->id,
            'loan_product_id' => $fixtures['product']->id,
            'branch_id' => $fixtures['branch']->id,
            'principal_amount' => 100000,
            'interest_amount' => 0,
            'total_repayment' => 100000,
            'principal_balance' => 100000,
            'interest_balance' => 0,
            'total_balance' => 100000,
            'number_of_installments' => 2,
            'installment_amount' => 50000,
            'first_payment_date' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
        ], $overrides));
    }

    /**
     * Two installments: one due before the cutoff, one after.
     */
    private function schedule(Loan $loan, float $totalDue = 50000): void
    {
        $loan->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => $totalDue,
            'interest_due' => 0,
            'total_due' => $totalDue,
            'status' => 'overdue',
        ]);
        $loan->installments()->create([
            'installment_number' => 2,
            'due_date' => now()->addMonth()->toDateString(),
            'principal_due' => $totalDue,
            'interest_due' => 0,
            'total_due' => $totalDue,
            'status' => 'upcoming',
        ]);
    }

    public function test_bulk_completion_marks_installments_due_before_cutoff_as_paid(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-1');
        $this->schedule($loan);

        $cutoff = now()->subDays(2);
        $result = app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], $cutoff);

        $this->assertSame(1, $result['loans']);
        $this->assertSame(1, $result['installments']);
        $this->assertEquals(50000, $result['amount']);
        $this->assertSame(0, $result['loans_settled']);

        $installments = $loan->installments()->orderBy('installment_number')->get();
        $this->assertSame('paid', $installments[0]->status);
        $this->assertEquals(50000, $installments[0]->total_paid);
        // Installments after the cutoff are untouched.
        $this->assertSame('upcoming', $installments[1]->status);
        $this->assertEquals(0, $installments[1]->total_paid);

        $loan->refresh();
        $this->assertEquals(50000, $loan->principal_balance);
        $this->assertEquals(50000, $loan->total_balance);
        $this->assertSame('active', $loan->status->value);

        $this->assertDatabaseHas('payments', ['loan_id' => $loan->id, 'amount' => 50000, 'status' => 'posted']);
    }

    public function test_cutoff_is_inclusive_and_paid_at_the_cutoff_date(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-2');
        $loan->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => 100000,
            'interest_due' => 0,
            'total_due' => 100000,
            'status' => 'overdue',
        ]);

        app(PaymentService::class)->markRepaymentsCompletedUpTo(
            $fixtures['user'],
            now()->subMonth()
        );

        $this->assertSame('paid', $loan->installments()->first()->status);
        $loan->refresh();
        $this->assertEquals(0, $loan->total_balance);
        $this->assertSame('settled', $loan->status->value);
        $this->assertDatabaseHas('payments', [
            'loan_id' => $loan->id,
            'paid_at' => now()->subMonth()->endOfDay(),
        ]);
    }

    public function test_fully_covered_loans_are_settled_and_partially_covered_loans_are_not(): void
    {
        $fixtures = $this->fixtures();
        $full = $this->loan($fixtures, 'MRC-L-3');
        $full->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => 100000,
            'interest_due' => 0,
            'total_due' => 100000,
            'status' => 'overdue',
        ]);
        $partial = $this->loan($fixtures, 'MRC-L-4');
        $partial->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => 50000,
            'interest_due' => 0,
            'total_due' => 50000,
            'status' => 'overdue',
        ]);
        $partial->installments()->create([
            'installment_number' => 2,
            'due_date' => now()->addMonth()->toDateString(),
            'principal_due' => 50000,
            'interest_due' => 0,
            'total_due' => 50000,
            'status' => 'upcoming',
        ]);

        $result = app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], now());

        $this->assertSame(2, $result['loans']);
        $this->assertSame(1, $result['loans_settled']);

        $full->refresh();
        $this->assertEquals(0, $full->total_balance);
        $this->assertSame('settled', $full->status->value);

        $partial->refresh();
        $this->assertEquals(50000, $partial->total_balance);
        $this->assertSame('active', $partial->status->value);
        $this->assertSame('paid', $partial->installments()->where('installment_number', 1)->first()->status);
        $this->assertNotSame('paid', $partial->installments()->where('installment_number', 2)->first()->status);
    }

    public function test_estimate_reports_counts_without_writing(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-5');
        $this->schedule($loan);

        $estimate = app(PaymentService::class)->estimateRepaymentsCompletedUpTo(now());

        $this->assertSame(1, $estimate['loans']);
        $this->assertSame(1, $estimate['installments']);
        $this->assertEquals(50000, $estimate['amount']);
        $this->assertSame(0, $estimate['loans_settled']);
        $this->assertCount(1, $estimate['samples']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('overdue', LoanInstallment::where('installment_number', 1)->first()->status);
    }

    public function test_branch_filter_limits_the_affected_loans(): void
    {
        $fixtures = $this->fixtures();
        $first = $this->loan($fixtures, 'MRC-L-6');
        $this->schedule($first);

        $otherRegion = Region::create(['name' => 'Other', 'code' => 'R-OTHER']);
        $otherArea = Area::create(['region_id' => $otherRegion->id, 'name' => 'Other', 'code' => 'A-OTHER']);
        $otherBranch = Branch::create(['area_id' => $otherArea->id, 'branch_code' => 'OTH-B', 'branch_name' => 'Other Branch']);
        $otherGroup = MemberGroup::create(['branch_id' => $otherBranch->id, 'group_code' => 'OTH-G', 'group_name' => 'Other Group']);
        $otherMember = Member::create([
            'branch_id' => $otherBranch->id,
            'group_id' => $otherGroup->id,
            'membership_number' => 'OTH-M',
            'first_name' => 'Binu',
            'last_name' => 'Juma',
            'phone' => '255755000001',
        ]);
        $otherApplication = LoanApplication::create([
            'application_number' => 'OTH-A',
            'member_id' => $otherMember->id,
            'group_id' => $otherGroup->id,
            'branch_id' => $otherBranch->id,
            'loan_product_id' => $fixtures['product']->id,
            'requested_amount' => 100000,
            'duration_months' => 2,
            'status' => 'approved',
            'created_by' => $fixtures['user']->id,
        ]);
        $otherLoan = Loan::create([
            'loan_number' => 'OTH-L',
            'loan_application_id' => $otherApplication->id,
            'member_id' => $otherMember->id,
            'group_id' => $otherGroup->id,
            'loan_product_id' => $fixtures['product']->id,
            'branch_id' => $otherBranch->id,
            'principal_amount' => 100000,
            'interest_amount' => 0,
            'total_repayment' => 100000,
            'principal_balance' => 100000,
            'interest_balance' => 0,
            'total_balance' => 100000,
            'number_of_installments' => 1,
            'installment_amount' => 100000,
            'first_payment_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        $otherLoan->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => 100000,
            'interest_due' => 0,
            'total_due' => 100000,
            'status' => 'overdue',
        ]);

        $result = app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], now(), $fixtures['branch']->id);

        $this->assertSame(1, $result['loans']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $first->installments()->where('installment_number', 1)->first()->status);
        $this->assertSame('overdue', $otherLoan->installments()->first()->status);
        $otherLoan->refresh();
        $this->assertEquals(100000, $otherLoan->total_balance);
    }

    public function test_settled_and_waived_loans_are_ignored(): void
    {
        $fixtures = $this->fixtures();
        $settled = $this->loan($fixtures, 'MRC-L-7', ['status' => 'settled']);
        $this->schedule($settled);
        $waived = $this->loan($fixtures, 'MRC-L-8');
        $waived->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => 100000,
            'interest_due' => 0,
            'total_due' => 100000,
            'status' => 'waived',
        ]);

        $result = app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], now());

        $this->assertSame(0, $result['loans']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('waived', $waived->installments()->first()->status);
    }

    public function test_future_cutoff_date_is_rejected(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-9');
        $this->schedule($loan);

        $this->expectException(\DomainException::class);
        app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], now()->addDay());
    }

    public function test_future_cutoff_date_is_rejected_over_http(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $tomorrow = now()->addDay()->toDateString();
        $this->actingAs($admin)
            ->get(route('admin.system.data.repayments.mark-completed.preview', ['cutoff_date' => $tomorrow]))
            ->assertSessionHasErrors('cutoff_date');
        $this->actingAs($admin)
            ->post(route('admin.system.data.repayments.mark-completed'), [
                'cutoff_date' => $tomorrow,
                'expected_phrase' => 'MARK REPAYMENTS COMPLETE',
                'confirmation_phrase' => 'MARK REPAYMENTS COMPLETE',
            ])
            ->assertSessionHasErrors('cutoff_date');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_running_twice_is_idempotent(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-10');
        $this->schedule($loan);

        $service = app(PaymentService::class);
        $first = $service->markRepaymentsCompletedUpTo($fixtures['user'], now());
        $second = $service->markRepaymentsCompletedUpTo($fixtures['user'], now());

        $this->assertSame(1, $first['loans']);
        $this->assertSame(0, $second['loans']);
        $this->assertDatabaseCount('payments', 1);
        $loan->refresh();
        $this->assertEquals(50000, $loan->total_balance);
    }

    public function test_partial_installments_only_need_the_remaining_amount(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-11', [
            'principal_balance' => 60000,
            'total_balance' => 60000,
        ]);
        $loan->installments()->create([
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'principal_due' => 100000,
            'interest_due' => 0,
            'total_due' => 100000,
            'principal_paid' => 40000,
            'interest_paid' => 0,
            'total_paid' => 40000,
            'status' => 'partially_paid',
        ]);

        $result = app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], now());

        $this->assertEquals(60000, $result['amount']);
        $installment = $loan->installments()->first();
        $this->assertSame('paid', $installment->status);
        $this->assertEquals(100000, $installment->total_paid);
        $loan->refresh();
        $this->assertEquals(0, $loan->total_balance);
        $this->assertSame('settled', $loan->status->value);
    }

    public function test_activity_log_records_the_bulk_completion(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-12');
        $this->schedule($loan);

        app(PaymentService::class)->markRepaymentsCompletedUpTo($fixtures['user'], now());

        $this->assertDatabaseHas('activity_log', ['description' => 'Bulk repayment completion completed']);
        $this->assertDatabaseHas('activity_log', ['description' => 'Repayments completed up to date']);
    }

    public function test_admin_can_preview_and_execute_over_http(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'MRC-L-13');
        $this->schedule($loan);

        $admin = User::factory()->create(['branch_id' => $fixtures['branch']->id]);
        $admin->assignRole('super_admin');
        $plain = User::factory()->create(['branch_id' => $fixtures['branch']->id]);

        $this->actingAs($plain)
            ->get(route('admin.system.data.repayments.mark-completed.preview', ['cutoff_date' => today()->toDateString()]))
            ->assertForbidden();
        $this->actingAs($plain)
            ->post(route('admin.system.data.repayments.mark-completed'), [
                'cutoff_date' => today()->toDateString(),
                'expected_phrase' => 'MARK REPAYMENTS COMPLETE',
                'confirmation_phrase' => 'MARK REPAYMENTS COMPLETE',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.system.data.repayments.mark-completed.preview', ['cutoff_date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('data.installments', 1)
            ->assertJsonPath('data.loans', 1);

        // The confirmation phrase must match exactly.
        $this->actingAs($admin)
            ->post(route('admin.system.data.repayments.mark-completed'), [
                'cutoff_date' => today()->toDateString(),
                'expected_phrase' => 'MARK REPAYMENTS COMPLETE',
                'confirmation_phrase' => 'wrong phrase',
            ])
            ->assertSessionHasErrors('confirmation_phrase');
        $this->assertDatabaseCount('payments', 0);

        $this->actingAs($admin)
            ->post(route('admin.system.data.repayments.mark-completed'), [
                'cutoff_date' => today()->toDateString(),
                'expected_phrase' => 'MARK REPAYMENTS COMPLETE',
                'confirmation_phrase' => 'MARK REPAYMENTS COMPLETE',
                'branch_id' => $fixtures['branch']->id,
            ])
            ->assertRedirect(route('admin.system.data'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $loan->installments()->where('installment_number', 1)->first()->status);
    }

    public function test_system_data_page_exposes_the_bulk_completion_form(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('admin.system.data'))
            ->assertOk()
            ->assertSee('Mark Repayments Completed Up To Date')
            ->assertSee('name="cutoff_date"', false)
            ->assertSee('MARK REPAYMENTS COMPLETE')
            ->assertSee('id="markRepaymentsExecuteBtn"', false)
            ->assertSee('type="submit"', false)
            ->assertSee('name="confirmation_phrase"', false)
            ->assertSee(route('admin.system.data.repayments.mark-completed'), false);
    }

    public function test_cutoff_date_is_required_over_http(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.system.data.repayments.mark-completed'), [
                'expected_phrase' => 'MARK REPAYMENTS COMPLETE',
                'confirmation_phrase' => 'MARK REPAYMENTS COMPLETE',
            ])
            ->assertSessionHasErrors('cutoff_date');
    }
}