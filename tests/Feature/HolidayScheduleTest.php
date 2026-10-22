<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Holiday;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\HolidayScheduleService;
use App\Services\PaymentService;
use App\Services\RepaymentScheduleService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayScheduleTest extends TestCase
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

    public function test_paid_schedules_are_forwarded_without_changing_payment_data_and_repeat_is_safe(): void
    {
        $fixtures = $this->fixtures();
        $loan = $this->loan($fixtures, 'HOL-1');
        $loan->product->update(['repayment_frequency' => 'weekly']);
        app(RepaymentScheduleService::class)->generate($loan, Carbon::parse('2026-10-14'));
        $first = $loan->installments()->orderBy('installment_number')->first();
        $payment = app(PaymentService::class)->post($loan, $fixtures['user'], 50000, ['payment_method' => 'cash']);
        $paymentBefore = $payment->fresh()->getAttributes();
        $allocationBefore = $payment->allocations()->first()->getAttributes();
        Holiday::create(['date' => '2026-10-14', 'name' => 'Nyerere Day']);
        Holiday::create(['date' => '2026-10-21', 'name' => 'Special holiday']);
        $service = app(HolidayScheduleService::class);
        $this->assertSame(2, $service->correct($loan, true));
        $this->assertSame('2026-10-28', $first->fresh()->due_date->toDateString());
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertEquals(50000, $first->fresh()->total_paid);
        $this->assertSame('2026-11-04', $loan->fresh()->maturity_date->toDateString());
        $this->assertSame(0, $service->correct($loan, true));
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertSame($allocationBefore, $payment->allocations()->first()->getAttributes());
    }

    public function test_generation_obeys_setting_and_paid_rows_are_excluded_by_default(): void
    {
        $loan = $this->loan($this->fixtures(), 'HOL-2');
        Holiday::create(['date' => '2026-10-14', 'name' => 'Nyerere Day']);
        SystemSetting::set('forward_holiday_repayments', '1');
        app(RepaymentScheduleService::class)->generate($loan, Carbon::parse('2026-10-14'));
        $first = $loan->installments()->orderBy('installment_number')->first();
        $this->assertSame('2026-10-21', $first->due_date->toDateString());
        $first->update(['due_date' => '2026-10-14', 'total_paid' => 50000, 'status' => 'paid']);
        $this->assertSame(0, app(HolidayScheduleService::class)->correct($loan));
        $this->assertSame('2026-10-14', $first->fresh()->due_date->toDateString());
    }

    public function test_import_is_repeatable_and_includes_easter(): void
    {
        $service = app(HolidayScheduleService::class);
        $service->importTanzania(2026);
        $service->importTanzania(2026);
        $this->assertDatabaseCount('holidays', 13);
        $this->assertDatabaseHas('holidays', ['date' => '2026-04-03', 'name' => 'Good Friday']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-04-06', 'name' => 'Easter Monday']);
    }

    public function test_administrator_can_correct_existing_schedules_from_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $fixtures = $this->fixtures();
        $fixtures['user']->assignRole('super_admin');
        $loan = $this->loan($fixtures, 'HOL-WEB');
        app(RepaymentScheduleService::class)->generate($loan, Carbon::parse('2026-10-14'));
        Holiday::create(['date' => '2026-10-14', 'name' => 'Nyerere Day']);
        $this->actingAs($fixtures['user'])->post(route('admin.system.holidays.correct'), [
            'from' => '2026-10-14', 'to' => '2026-10-14', 'include_paid' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-21', $loan->installments()->orderBy('installment_number')->first()->due_date->toDateString());
        $this->get(route('admin.system.holidays.index'))->assertOk();
    }
}
