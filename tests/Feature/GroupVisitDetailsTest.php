<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupCollection;
use App\Models\GroupVisit;
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

class GroupVisitDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MemberGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $region = Region::create(['name' => 'Region', 'code' => 'GV-R']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area', 'code' => 'GV-A']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'GV-B', 'branch_name' => 'Visits Branch']);

        $officer = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Juma Field Officer']);
        $officer->assignRole('loan_officer');

        $this->group = MemberGroup::create([
            'branch_id' => $branch->id,
            'group_code' => 'GV-001',
            'group_name' => 'Umoja Wanavyote',
            'meeting_day' => 'Thursday',
            'meeting_time' => '09:30:00',
            'ward' => 'Mtaa Z',
            'district' => 'Ilala',
            'location' => 'Kiji cha Umoja',
            'loan_officer_id' => $officer->id,
        ]);

        $this->admin = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Hadi Supervisor']);
        $this->admin->assignRole('super_admin');
    }

    public function test_the_visit_record_page_renders_the_visit_its_notes_and_the_group_snapshot(): void
    {
        $visit = GroupVisit::create([
            'group_id' => $this->group->id,
            'user_id' => $this->admin->id,
            'visit_date' => now()->subDays(3)->toDateString(),
            'purpose' => 'Loan appraisal',
            'location' => 'Kiji cha Umoja',
            'notes' => "Members requested a new disbursement window.\nSecurity verified.",
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.group-visits.show', $visit))
            ->assertOk()
            ->assertSee('Umoja Wanavyote')
            ->assertSee('Loan appraisal')
            ->assertSee("Members requested a new disbursement window.\nSecurity verified.")
            ->assertSee('Kiji cha Umoja')
            ->assertSee(__('Visit details'))
            ->assertSee(__('Field notes'))
            ->assertSee(__('Visit history'))
            ->assertSee(__('Group snapshot'))
            ->assertSee('Kiji cha Umoja', false)
            ->assertSee(route('admin.groups.show', $this->group), false)
            ->assertSee(route('admin.group-visits.create', ['group_id' => $this->group->id]), false)
            ->assertSee(route('admin.group-visits.destroy', $visit), false)
            ->assertSee(__('This visit'))
            ->assertSee('1 of 1');
    }

    public function test_the_snapshot_counts_members_loans_outstanding_balance_and_savings(): void
    {
        $activeMember = Member::create([
            'membership_number' => 'GV-M1',
            'branch_id' => $this->group->branch_id,
            'group_id' => $this->group->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255700111222',
            'status' => 'active',
        ]);
        Member::create([
            'membership_number' => 'GV-M2',
            'branch_id' => $this->group->branch_id,
            'group_id' => $this->group->id,
            'first_name' => 'Neema',
            'last_name' => 'Musa',
            'phone' => '255700333444',
            'status' => 'inactive',
        ]);

        $product = LoanProduct::create(['code' => 'GV-P1', 'name' => 'Main loan']);
        $application = LoanApplication::create([
            'application_number' => 'GV-APP-1',
            'member_id' => $activeMember->id,
            'loan_product_id' => $product->id,
            'group_id' => $this->group->id,
            'branch_id' => $this->group->branch_id,
            'requested_amount' => 500000,
            'duration_months' => 20,
        ]);
        Loan::create([
            'loan_number' => 'GV-LN-1',
            'loan_application_id' => $application->id,
            'member_id' => $activeMember->id,
            'group_id' => $this->group->id,
            'loan_product_id' => $product->id,
            'branch_id' => $this->group->branch_id,
            'principal_amount' => 500000,
            'total_repayment' => 600000,
            'principal_balance' => 400000,
            'total_balance' => 400000,
            'status' => 'active',
        ]);

        $settledApplication = LoanApplication::create([
            'application_number' => 'GV-APP-2',
            'member_id' => $activeMember->id,
            'loan_product_id' => $product->id,
            'group_id' => $this->group->id,
            'branch_id' => $this->group->branch_id,
            'requested_amount' => 100000,
            'duration_months' => 10,
            'status' => 'disbursed',
        ]);
        Loan::create([
            'loan_number' => 'GV-LN-2',
            'loan_application_id' => $settledApplication->id,
            'member_id' => $activeMember->id,
            'group_id' => $this->group->id,
            'loan_product_id' => $product->id,
            'branch_id' => $this->group->branch_id,
            'principal_amount' => 100000,
            'total_repayment' => 120000,
            'principal_balance' => 0,
            'total_balance' => 0,
            'status' => 'settled',
        ]);

        GroupCollection::create([
            'group_id' => $this->group->id,
            'collection_date' => now()->subMonth()->toDateString(),
            'expected_amount' => 400000,
            'collected_amount' => 300000,
        ]);

        $visit = GroupVisit::create([
            'group_id' => $this->group->id,
            'user_id' => $this->admin->id,
            'visit_date' => now()->subDay()->toDateString(),
            'purpose' => 'Collections follow-up',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.group-visits.show', $visit))
            ->assertOk()
            ->assertSee(__('Members in group'))
            ->assertSee('2')                          // total members
            ->assertSee('1 '.__('active'))            // active members
            ->assertSee(__('Group loans'))
            ->assertSee(__('Outstanding balance'))
            ->assertSee('TZS 400,000.00')             // settled loans are excluded
            ->assertSee('width:33.33', false)
            ->assertSee(__('Savings collected'))
            ->assertSee('TZS 300,000.00')
            ->assertSee('75.0%');
    }

    public function test_it_links_the_previous_and_next_visits_and_lists_the_group_history(): void
    {
        $older = GroupVisit::create([
            'group_id' => $this->group->id,
            'user_id' => $this->admin->id,
            'visit_date' => now()->subDays(40)->toDateString(),
            'purpose' => 'Group formation',
        ]);
        $current = GroupVisit::create([
            'group_id' => $this->group->id,
            'user_id' => $this->admin->id,
            'visit_date' => now()->subDays(12)->toDateString(),
            'purpose' => 'Loan appraisal',
        ]);
        $newer = GroupVisit::create([
            'group_id' => $this->group->id,
            'user_id' => $this->admin->id,
            'visit_date' => now()->subDay()->toDateString(),
            'purpose' => 'Repayment counselling',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.group-visits.show', $current))
            ->assertOk()
            ->assertSee(route('admin.group-visits.show', $older), false)
            ->assertSee(route('admin.group-visits.show', $newer), false)
            ->assertSee('Group formation')
            ->assertSee('Repayment counselling')
            ->assertSee(__('Next visit to this group'))
            ->assertSee('2 of 3')                     // visit numbering by date
            ->assertSee(route('admin.group-visits.index', ['group_id' => $this->group->id]), false);
    }

    public function test_a_visit_without_notes_or_history_shows_the_empty_states(): void
    {
        $visit = GroupVisit::create([
            'group_id' => $this->group->id,
            'user_id' => $this->admin->id,
            'visit_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.group-visits.show', $visit))
            ->assertOk()
            ->assertSee(__('No notes were captured for this visit.'))
            ->assertSee(__('This is the first visit recorded for this group.'))
            ->assertSee(__('Not specified'))
            ->assertSee(__('No purpose captured'))
            ->assertDontSee(__('Previous visit'))
            ->assertDontSee(__('Next visit to this group'));
    }
}
