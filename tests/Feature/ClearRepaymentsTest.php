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
use App\Services\PaymentService;
use App\Services\RepaymentScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearRepaymentsTest extends TestCase
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

    public function test_clear_reverses_only_selected_payment_date_and_is_repeatable(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $user = $fixtures['user'];
        $user->assignRole('super_admin');
        $loan = $this->loan($fixtures, 'CLR');
        app(RepaymentScheduleService::class)->generate($loan, Carbon::parse('2026-10-01'));
        $service = app(PaymentService::class);
        $selected = $service->post($loan, $user, 20000, ['payment_method' => 'cash', 'paid_at' => '2026-10-14 23:59:59']);
        $other = $service->post($loan, $user, 10000, ['payment_method' => 'cash', 'paid_at' => '2026-10-15 00:00:00']);
        $this->actingAs($user)->get(route('admin.repayments.clear', ['date' => '2026-10-14']))
            ->assertOk()->assertSee($selected->payment_number)->assertDontSee($other->payment_number);
        $data = ['date' => '2026-10-14', 'reason' => 'Incorrect imported repayments'];
        $this->post(route('admin.repayments.clear.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('reversed', $selected->fresh()->status);
        $this->assertSame('posted', $other->fresh()->status);
        $this->assertEquals(90000, $loan->fresh()->total_balance);
        $this->assertEquals(10000, $loan->installments()->sum('total_paid'));
        $this->post(route('admin.repayments.clear.store'), $data)->assertRedirect();
        $this->assertEquals(90000, $loan->fresh()->total_balance);
    }

    public function test_clear_requires_admin_and_valid_date_and_reason(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $user = $fixtures['user'];
        $this->actingAs($user)->post(route('admin.repayments.clear.store'), ['date' => '2026-10-14', 'reason' => 'Mistake'])
            ->assertForbidden();
        $user->assignRole('head_office_admin');
        $this->post(route('admin.repayments.clear.store'), ['date' => 'invalid'])->assertSessionHasErrors('date');
        $this->post(route('admin.repayments.clear.store'), ['date' => '2026-10-14'])->assertSessionHasErrors('reason');
    }

    public function test_branch_filter_leaves_other_branch_payments_posted(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $a = $this->fixtures('CA');
        $b = $this->fixtures('CB');
        $a['user']->assignRole('super_admin');
        $service = app(PaymentService::class);
        $payments = [];
        foreach ([$a, $b] as $index => $fixtures) {
            $loan = $this->loan($fixtures, 'BR-'.$index);
            app(RepaymentScheduleService::class)->generate($loan, Carbon::parse('2026-10-01'));
            $payments[] = $service->post($loan, $fixtures['user'], 10000, ['payment_method' => 'cash', 'paid_at' => '2026-10-14 12:00:00']);
        }
        $this->actingAs($a['user'])->post(route('admin.repayments.clear.store'), [
            'date' => '2026-10-14', 'branch_id' => $a['branch']->id, 'reason' => 'Wrong branch import',
        ])->assertRedirect();
        $this->assertSame('reversed', $payments[0]->fresh()->status);
        $this->assertSame('posted', $payments[1]->fresh()->status);
    }
}
