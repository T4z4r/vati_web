<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\GroupMembership;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanTerm;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private MemberGroup $group;

    private LoanProduct $product;

    private LoanTerm $term;

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
        $this->term = LoanTerm::create(['version' => 'TEST-1', 'title' => 'Test terms', 'body' => 'Test declaration', 'effective_from' => today(), 'is_active' => true]);
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
        foreach (['/admin', '/admin/organization', '/admin/users', '/admin/users/create', '/admin/roles/permissions', '/admin/groups', '/admin/groups/create', '/admin/members', '/admin/members/create', '/admin/loan-products', '/admin/loan-products/create', '/admin/loan-applications', '/admin/loan-applications/create', '/admin/loans', '/admin/reports'] as $uri) {
            $response = $this->get($uri);
            $this->assertSame(200, $response->getStatusCode(), "{$uri} should render successfully.");
        }
    }

    public function test_management_financial_summary_is_hidden_from_operational_staff(): void
    {
        $loanOfficer = User::factory()->create(['branch_id' => $this->branch->id]);
        $loanOfficer->assignRole('loan_officer');

        $this->actingAs($loanOfficer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('Management financial summary'))
            ->assertDontSee(__('Total loan portfolio'))
            ->assertDontSee(__('Total posted payments'))
            ->assertDontSee(__('Repayment profit / loss'))
            ->assertDontSee(__('Total loan disbursement'))
            ->assertDontSee(__('Total loan applications'));

        $branchManager = User::factory()->create(['branch_id' => $this->branch->id]);
        $branchManager->assignRole('branch_manager');

        $this->actingAs($branchManager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Management financial summary'));
    }

    public function test_admin_forms_load_select2_and_placeholder_enhancements(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/loan-applications/create')
            ->assertOk()
            ->assertSee('select2@4.1.0-rc.0', false)
            ->assertSee('select:not([data-select2="false"])', false)
            ->assertSee('field.placeholder = label', false);
    }

    public function test_super_admin_can_assign_permissions_to_roles(): void
    {
        $cashier = Role::findByName('cashier');

        $this->actingAs($this->admin)
            ->get(route('admin.roles.permissions.index'))
            ->assertOk()
            ->assertSee('Role permission assignment')
            ->assertSee('Save role permissions');

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Roles &amp; Permissions', false)
            ->assertDontSee('Role permission assignment');

        $this->put(route('admin.roles.permissions.update', $cashier), [
            'permissions' => ['view-dashboard', 'view-reports'],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['view-dashboard', 'view-reports'],
            $cashier->fresh()->permissions->pluck('name')->all()
        );

        $superAdmin = Role::findByName('super_admin');
        $superAdminPermissionCount = $superAdmin->permissions()->count();
        $this->put(route('admin.roles.permissions.update', $superAdmin), ['permissions' => []])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame($superAdminPermissionCount, $superAdmin->fresh()->permissions()->count());

        $headOfficeAdmin = User::factory()->create(['branch_id' => null]);
        $headOfficeAdmin->assignRole('head_office_admin');
        $this->actingAs($headOfficeAdmin)
            ->put(route('admin.roles.permissions.update', $cashier), ['permissions' => ['view-dashboard']])
            ->assertForbidden();
    }

    public function test_web_member_and_application_creation_workflow(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);
        $this->post('/admin/members', ['branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255712000001', 'admission_date' => today()->format('Y-m-d'), 'photo' => UploadedFile::fake()->image('asha.jpg', 300, 300), 'nominees' => [
            ['name' => 'Neema Musa', 'relationship' => 'Daughter', 'percentage' => 60],
            ['name' => 'Juma Musa', 'relationship' => 'Son', 'percentage' => 40],
        ], 'family_members' => [
            ['name' => 'Baraka Musa', 'gender' => 'Male', 'age' => 12, 'relationship' => 'Son', 'education' => 'Primary'],
            ['name' => 'Neema Musa', 'gender' => 'Female', 'age' => 9, 'relationship' => 'Daughter', 'education' => 'Primary'],
        ], 'assets' => [
            ['name' => 'Television', 'category' => 'Household', 'quantity' => 1, 'estimated_value' => 350000, 'description' => 'Working condition'],
            ['name' => 'Goats', 'category' => 'Livestock', 'quantity' => 3, 'estimated_value' => 450000],
        ]])->assertRedirect();
        $member = Member::firstOrFail();
        $originalPhotoPath = $member->photo_path;
        Storage::disk('public')->assertExists($originalPhotoPath);
        $this->assertDatabaseHas('group_memberships', ['member_id' => $member->id, 'group_id' => $this->group->id, 'status' => 'active']);
        $this->assertDatabaseCount('member_nominees', 2);
        $this->assertDatabaseCount('member_family_members', 2);
        $this->assertDatabaseCount('member_assets', 2);
        $this->get(route('admin.members.show', $member))->assertOk()->assertSeeInOrder(['Asha', 'Musa'])->assertSee(basename($originalPhotoPath))->assertSee('Neema Musa')->assertSee('Juma Musa')->assertSee('Baraka Musa')->assertSee('Television')->assertSee('Edit nominees');
        $this->get(route('admin.members.edit', $member))->assertOk()->assertSee('Current member photograph')->assertSee('Nominees / Wateule')->assertSee('Applicant Family Members')->assertSee('Family Assets')->assertSee('Baraka Musa')->assertSee('Television');
        $this->put(route('admin.members.update', $member), [
            'branch_id' => $this->branch->id,
            'group_id' => $this->group->id,
            'first_name' => 'Asha',
            'last_name' => 'Musa',
            'phone' => '255712000001',
            'status' => 'active',
            'photo' => UploadedFile::fake()->image('asha-updated.png', 400, 400),
            'nominees' => [
                ['name' => 'Rehema Musa', 'relationship' => 'Daughter', 'percentage' => 100],
            ],
            'family_members' => [
                ['name' => 'Amani Musa', 'gender' => 'Male', 'age' => 15, 'relationship' => 'Son', 'occupation' => 'Student'],
            ],
            'assets' => [
                ['name' => 'Refrigerator', 'category' => 'Household', 'quantity' => 1, 'estimated_value' => 600000],
            ],
        ])->assertRedirect(route('admin.members.show', $member));
        $member->refresh();
        Storage::disk('public')->assertMissing($originalPhotoPath);
        Storage::disk('public')->assertExists($member->photo_path);
        $this->assertDatabaseCount('member_nominees', 1);
        $this->assertDatabaseHas('member_nominees', ['member_id' => $member->id, 'name' => 'Rehema Musa', 'percentage' => 100]);
        $this->assertDatabaseCount('member_family_members', 1);
        $this->assertDatabaseHas('member_family_members', ['member_id' => $member->id, 'name' => 'Amani Musa']);
        $this->assertDatabaseCount('member_assets', 1);
        $this->assertDatabaseHas('asset_types', ['name' => 'Refrigerator', 'category' => 'Household']);
        $onboardingWitness = $this->member('Witness Member', '255712000099');
        $this->get(route('admin.groups.show', $this->group))->assertOk()->assertSee('Kinondoni Group')->assertSee(basename($member->photo_path), false)->assertSee('data-member-photo', false);
        $this->get(route('admin.loan-products.show', $this->product))->assertOk()->assertSee('Weekly Loan');
        $this->get(route('admin.loan-applications.create', ['member_id' => $member->id]))
            ->assertOk()
            ->assertSee('Applicant profile (auto-populated)')
            ->assertSee('Applicant photograph')
            ->assertSee(basename($member->photo_path), false)
            ->assertSee('Projected repayment schedule')
            ->assertSee('schedule-body', false)
            ->assertSee('data-frequency="weekly"', false)
            ->assertSee('memberProfiles', false)
            ->assertSee('Guarantors / Wadhamini')
            ->assertSee('Group Witnesses / Mashahidi wa Kikundi')
            ->assertSee('Witness Member')
            ->assertSee('Asha Musa');

        $applicationPayload = $this->applicationPayload($member);
        $applicationPayload['guarantors'] = [
            ['guarantor_type' => 'family', 'name' => 'Musa Juma', 'relationship' => 'Father', 'phone' => '255700111222', 'national_id' => 'G-001'],
            ['guarantor_type' => 'non_family', 'name' => 'Rehema Ally', 'relationship' => 'Friend', 'phone' => '255700333444', 'national_id' => 'G-002'],
        ];
        $applicationPayload['witness_member_ids'] = [$onboardingWitness->id];
        $this->post('/admin/loan-applications', $applicationPayload)->assertRedirect();
        $this->assertDatabaseHas('loan_applications', ['member_id' => $member->id, 'group_id' => $this->group->id, 'branch_id' => $this->branch->id, 'status' => 'draft']);
        $application = $member->loanApplications()->firstOrFail();
        $this->assertSame(0, $application->utilizations()->count());
        $this->assertDatabaseCount('loan_guarantors', 2);
        $this->assertDatabaseHas('loan_group_witnesses', ['loan_application_id' => $application->id, 'member_id' => $onboardingWitness->id]);
        $this->get(route('admin.members.show', $member))->assertOk()->assertSee('Musa Juma')->assertSee('Rehema Ally')->assertSee('Witness Member')->assertSee('Manage');
        $this->get(route('admin.loan-applications.edit', $application))->assertOk()->assertSee('Musa Juma')->assertSee('Rehema Ally')->assertSee('Witness Member');
        $this->get(route('admin.loan-applications.show', $application))
            ->assertOk()
            ->assertSee($application->application_number)
            ->assertSee('Download application PDF')
            ->assertSee(basename($member->photo_path), false)
            ->assertSee('data-member-photo', false)
            ->assertSeeInOrder([
                'Loan application identification',
                'Applicant personal profile',
                'Application terms and loan computation',
                'Applicant family members',
                'Family assets',
                'Income and expenditure assessment',
                'Use of loan amount',
                'Nominee information',
                'Guarantor declarations',
                'Document checklist',
                'Group witnesses',
                'Recommendations and verification',
            ]);
        $this->get(route('admin.loan-applications.export', $application))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('VATI-loan-application-'.$application->application_number.'.pdf');
        $updated = $this->applicationPayload($member);
        $updated['requested_amount'] = 1200000;
        $updated['utilizations'] = [['purpose' => 'Working capital', 'allocation_amount' => 1200000, 'current_asset_value' => 0]];
        $updated['guarantors'] = $application->guarantors()->orderBy('id')->get()->map(fn ($guarantor, $index) => [
            'id' => $guarantor->id,
            'guarantor_type' => $guarantor->guarantor_type,
            'name' => $index === 0 ? 'Musa Juma Updated' : $guarantor->name,
            'relationship' => $guarantor->relationship,
            'phone' => $guarantor->phone,
            'national_id' => $guarantor->national_id,
        ])->all();
        $updated['witness_member_ids'] = [$onboardingWitness->id];
        $this->put(route('admin.loan-applications.update', $application), $updated)->assertRedirect(route('admin.loan-applications.show', $application));
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'requested_amount' => 1200000]);
        $this->assertDatabaseHas('loan_utilizations', ['loan_application_id' => $application->id, 'allocation_amount' => 1200000]);
        $this->assertDatabaseCount('loan_guarantors', 2);
        $this->assertDatabaseHas('loan_guarantors', ['loan_application_id' => $application->id, 'name' => 'Musa Juma Updated']);
        $this->assertDatabaseHas('loan_group_witnesses', ['loan_application_id' => $application->id, 'member_id' => $onboardingWitness->id]);
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
        $borrower->update(['photo_path' => 'members/photos/borrower.jpg']);
        $firstWitness = $this->member('Witness One', '255713000002');
        $secondWitness = $this->member('Witness Two', '255713000003');

        $this->post('/admin/loan-applications', $this->applicationPayload($borrower))->assertRedirect();
        $application = $borrower->loanApplications()->firstOrFail();
        $this->makeCompliant($application);
        $this->post(route('admin.loan-applications.submit', $application))->assertRedirect();
        $this->post(route('admin.loan-applications.witnesses.store', $application), ['member_id' => $firstWitness->id])->assertRedirect();
        $this->post(route('admin.loan-applications.witnesses.store', $application), ['member_id' => $secondWitness->id])->assertRedirect();
        $this->get(route('admin.members.show', $borrower))
            ->assertOk()
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSee(route('admin.loan-applications.approve', $application), false)
            ->assertSee(route('admin.loan-applications.reject', $application), false);
        $this->post(route('admin.loan-applications.approve', $application), ['remarks' => 'Affordability and group confirmation accepted'])->assertRedirect();

        $loan = $application->refresh()->loan;
        $this->assertNotNull($loan);
        $this->get(route('admin.groups.show', $this->group))
            ->assertOk()
            ->assertSee('Members and loan balances')
            ->assertSee('View details')
            ->assertSee(number_format((float) $loan->total_balance, 2));
        $this->get(route('admin.loans.show', $loan))->assertOk()->assertSee($loan->loan_number)->assertSee('borrower.jpg', false)->assertSee('data-member-photo', false);
        $this->post(route('admin.loans.disburse', $loan), ['method' => 'cash', 'disbursed_at' => today()->format('Y-m-d'), 'first_payment_date' => today()->addWeek()->format('Y-m-d')])->assertRedirect();
        $this->assertGreaterThan(0, $loan->refresh()->installments()->count());
        $firstInstallment = $loan->installments()->orderBy('installment_number')->firstOrFail();
        $partialAmount = round((float) $firstInstallment->total_due / 2, 2);
        $this->get(route('admin.loans.show', $loan))->assertOk()->assertSee('Confirm repayment')->assertSee('loan_installment_id', false);
        $this->post(route('admin.payments.store', $loan), ['loan_installment_id' => $firstInstallment->id, 'amount' => $partialAmount, 'payment_method' => 'cash'])->assertRedirect();
        $this->assertDatabaseHas('payments', ['loan_id' => $loan->id, 'amount' => $partialAmount, 'status' => 'posted']);
        $this->assertSame('partially_paid', $firstInstallment->refresh()->status);
        $realizedRepaymentIncome = (float) $loan->payments()
            ->where('status', 'posted')
            ->with('allocations')
            ->get()
            ->flatMap->allocations
            ->sum(fn ($allocation) => (float) $allocation->interest_amount + (float) $allocation->penalty_amount);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Management financial summary'))
            ->assertSee(__('Total loan portfolio'))
            ->assertSee(__('Total posted payments'))
            ->assertSee(__('Repayment profit / loss'))
            ->assertSee(__('Total loan disbursement'))
            ->assertSee(__('Total loan applications'))
            ->assertSee(number_format((float) $loan->principal_amount, 2))
            ->assertSee(number_format($partialAmount, 2))
            ->assertSee(number_format($realizedRepaymentIncome, 2))
            ->assertSee(number_format(1000000, 2));
        $this->get(route('admin.loans.show', $loan))->assertOk()->assertSee('Payment history')->assertSee('partially paid');
        $this->get(route('admin.members.show', $borrower))
            ->assertOk()
            ->assertSee($loan->loan_number)
            ->assertSee('Confirm repayment')
            ->assertSee('loan_installment_id', false)
            ->assertSeeInOrder([
                'Complete loan history',
                'Loan information',
                'Loan security',
                'Installment collection',
                'Payments received',
                'Guarantors',
            ]);
    }

    private function member(string $name, string $phone): Member
    {
        [$first,$last] = array_pad(explode(' ', $name, 2), 2, 'Member');
        $member = Member::create(['membership_number' => 'VATI-M-'.now()->year.'-'.str_pad((string) (Member::count() + 1), 6, '0', STR_PAD_LEFT), 'branch_id' => $this->branch->id, 'group_id' => $this->group->id, 'first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'created_by' => $this->admin->id]);
        GroupMembership::create(['member_id' => $member->id, 'group_id' => $this->group->id, 'joined_at' => today(), 'status' => 'active']);

        return $member;
    }

    private function makeCompliant(LoanApplication $application): void
    {
        $application->update(['loan_term_id' => $this->term->id, 'consent_declaration' => $this->term->body, 'consented_at' => now()->subDays(4), 'cancellation_deadline' => now()->subDay(), 'applicant_signature_path' => 'tests/signature.png', 'applicant_thumbprint_path' => 'tests/thumbprint.png']);
        foreach (['family', 'non_family'] as $index => $type) {
            $application->guarantors()->create(['guarantor_type' => $type, 'name' => "Guarantor {$index}", 'relationship' => 'Relative', 'phone' => "25570000000{$index}", 'signature_path' => 'tests/signature.png', 'thumbprint_path' => 'tests/thumbprint.png', 'joint_photo_path' => 'tests/photo.png', 'declaration_text' => 'Accepted', 'declaration_accepted_at' => now()]);
        }
        $application->member->nominees()->create(['name' => 'Nominee', 'relationship' => 'Child', 'percentage' => 100, 'attested_at' => now()]);
        foreach (['member_identity', 'guarantor_identity'] as $type) {
            $application->documents()->create(['document_type' => $type, 'file_path' => "tests/{$type}.pdf", 'is_required' => true, 'verification_status' => 'verified', 'uploaded_by' => $this->admin->id, 'verified_by' => $this->admin->id, 'verified_at' => now()]);
        }
    }

    private function applicationPayload(Member $member): array
    {
        return [
            'member_id' => $member->id,
            'loan_product_id' => $this->product->id,
            'application_type' => 'main',
            'requested_amount' => 1000000,
            'duration_months' => 6,
            'existing_loan_balance' => 0,
            'refinancing_amount' => 0,
            'increment_amount' => 0,
            'loan_purpose' => 'Working capital expansion',
            'assessment' => ['core_business_income' => 500000, 'other_income' => 0, 'business_expenses' => 100000, 'household_expenses' => 100000, 'existing_external_debt' => 0],
        ];
    }
}
