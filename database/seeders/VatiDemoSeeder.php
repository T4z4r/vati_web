<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Branch;
use App\Models\CreditReview;
use App\Models\GroupAttendance;
use App\Models\GroupCollection;
use App\Models\GroupMeeting;
use App\Models\GroupMembership;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApproval;
use App\Models\LoanDisbursement;
use App\Models\LoanDocument;
use App\Models\LoanGroupWitness;
use App\Models\LoanGuarantor;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\LoanTerm;
use App\Models\LoanUtilization;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberKyc;
use App\Models\MemberNominee;
use App\Models\MemberSecurityAccount;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Region;
use App\Models\SecurityTransaction;
use App\Models\User;
use App\Services\LoanCalculatorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VatiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);

        DB::transaction(function () {
            $region = Region::updateOrCreate(
                ['code' => 'DSM'],
                ['name' => 'Dar es Salaam', 'status' => true]
            );

            $area = Area::updateOrCreate(
                ['code' => 'DSM-KIN'],
                ['region_id' => $region->id, 'name' => 'Kinondoni Area', 'status' => true]
            );

            $branch = Branch::updateOrCreate(
                ['branch_code' => 'VATI-BR-DSM-001'],
                [
                    'area_id' => $area->id,
                    'branch_name' => 'Kinondoni Branch',
                    'phone' => '+255 22 219 0001',
                    'email' => 'kinondoni@vati.co.tz',
                    'address' => 'Mikocheni, Kinondoni, Dar es Salaam',
                    'status' => true,
                ]
            );

            $manager = $this->user('branch.manager@vati.co.tz', 'Branch Manager', $branch->id, 'branch_manager');
            $loanOfficer = $this->user('loan.officer@vati.co.tz', 'Loan Officer', $branch->id, 'loan_officer');
            $creditOfficer = $this->user('credit.officer@vati.co.tz', 'Credit Officer', $branch->id, 'credit_officer');
            $cashier = $this->user('cashier@vati.co.tz', 'Cashier', $branch->id, 'cashier');

            $branch->update(['manager_id' => $manager->id]);

            $group = MemberGroup::updateOrCreate(
                ['group_code' => 'VATI-GRP-DSM-001'],
                [
                    'branch_id' => $branch->id,
                    'group_name' => 'Mikocheni Women Entrepreneurs',
                    'meeting_day' => 'Tuesday',
                    'meeting_time' => '09:00:00',
                    'region' => 'Dar es Salaam',
                    'district' => 'Kinondoni',
                    'ward' => 'Mikocheni',
                    'location' => 'Mikocheni Community Hall',
                    'loan_officer_id' => $loanOfficer->id,
                    'status' => true,
                ]
            );

            $members = collect([
                [
                    'membership_number' => 'VATI-M-2026-000001',
                    'first_name' => 'Asha',
                    'middle_name' => 'Juma',
                    'last_name' => 'Mwakalinga',
                    'phone' => '+255715000001',
                    'national_id' => '19870415123456789012',
                    'business_name' => 'Asha Fresh Foods',
                    'business_type' => 'Grocery stall',
                    'business_address' => 'Mikocheni Market, Stall 14',
                ],
                [
                    'membership_number' => 'VATI-M-2026-000002',
                    'first_name' => 'Neema',
                    'middle_name' => null,
                    'last_name' => 'Kassim',
                    'phone' => '+255715000002',
                    'national_id' => '19910220123456789012',
                    'business_name' => 'Neema Tailoring',
                    'business_type' => 'Tailoring',
                    'business_address' => 'Makumbusho Road',
                ],
                [
                    'membership_number' => 'VATI-M-2026-000003',
                    'first_name' => 'Rehema',
                    'middle_name' => null,
                    'last_name' => 'Msuya',
                    'phone' => '+255715000003',
                    'national_id' => '19881130123456789012',
                    'business_name' => 'Rehema Beauty Supplies',
                    'business_type' => 'Retail',
                    'business_address' => 'Mwenge Bus Stand',
                ],
            ])->map(fn (array $data) => $this->member($data, $branch->id, $group->id, $loanOfficer->id));

            $applicant = $members->first();
            $witnesses = $members->slice(1)->values();
            $product = LoanProduct::where('code', 'VATI-WEEKLY')->firstOrFail();
            $term = LoanTerm::where('is_active', true)->latest('effective_from')->first();

            $application = LoanApplication::updateOrCreate(
                ['application_number' => 'VATI-LAF-2026-000001'],
                [
                    'member_id' => $applicant->id,
                    'loan_product_id' => $product->id,
                    'group_id' => $group->id,
                    'branch_id' => $branch->id,
                    'application_type' => 'main',
                    'requested_amount' => 1200000,
                    'recommended_amount' => 1200000,
                    'duration_months' => 6,
                    'recommended_duration_months' => 6,
                    'existing_loan_balance' => 0,
                    'refinancing_amount' => 0,
                    'increment_amount' => 0,
                    'loan_purpose' => 'Purchase additional stock and a small cold-storage freezer.',
                    'business_summary' => 'Applicant runs a daily grocery stall with repeat household customers and weekly group accountability.',
                    'risk_level' => 'low',
                    'credit_review_attempt' => 1,
                    'status' => 'disbursed',
                    'created_by' => $loanOfficer->id,
                    'assigned_credit_officer_id' => $creditOfficer->id,
                    'submitted_at' => now()->subDays(16),
                    'loan_term_id' => $term?->id,
                    'consent_declaration' => $term?->body,
                    'consented_at' => now()->subDays(16),
                    'consented_ip' => '127.0.0.1',
                    'cancellation_deadline' => now()->subDays(13),
                ]
            );

            $this->applicationDetails($application, $applicant, $witnesses, $loanOfficer, $creditOfficer, $manager);
            $this->groupActivity($group, $members, $loanOfficer);
            $this->loan($application, $product, $applicant, $group, $branch, $manager, $cashier);
        });
    }

    private function user(string $email, string $name, int $branchId, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'branch_id' => $branchId,
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        $user->syncRoles($role);

        return $user;
    }

    private function member(array $data, int $branchId, int $groupId, int $createdBy): Member
    {
        $member = Member::updateOrCreate(
            ['membership_number' => $data['membership_number']],
            [
                'branch_id' => $branchId,
                'group_id' => $groupId,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'guardian_name' => 'Family Contact',
                'phone' => $data['phone'],
                'alternate_phone' => null,
                'national_id' => $data['national_id'],
                'date_of_birth' => now()->subYears(35)->toDateString(),
                'gender' => 'female',
                'marital_status' => 'married',
                'occupation' => 'Business owner',
                'nationality' => 'Tanzanian',
                'physical_address' => 'Kinondoni, Dar es Salaam',
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
                'ward' => 'Mikocheni',
                'street' => 'Mikocheni Street',
                'admission_date' => now()->subMonths(8)->toDateString(),
                'passbook_issue_date' => now()->subMonths(8)->addDays(2)->toDateString(),
                'status' => 'active',
                'created_by' => $createdBy,
            ]
        );

        GroupMembership::updateOrCreate(
            ['member_id' => $member->id, 'group_id' => $groupId],
            ['joined_at' => now()->subMonths(8)->toDateString(), 'left_at' => null, 'status' => 'active']
        );

        MemberKyc::updateOrCreate(
            ['member_id' => $member->id],
            [
                'mpesa_phone' => $data['phone'],
                'bank_account_number' => '015001001'.$member->id,
                'bank_account_name' => trim("{$data['first_name']} {$data['last_name']}"),
                'bank_name' => 'CRDB Bank',
                'house_number' => 'MCK-'.$member->id,
                'police_station' => 'Mikocheni Police Post',
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'business_address' => $data['business_address'],
                'household_monthly_income' => 980000,
                'household_monthly_expenses' => 430000,
                'number_of_dependants' => 3,
                'head_of_household' => trim("{$data['first_name']} {$data['last_name']}"),
                'house_ownership_status' => 'rented',
                'house_roof_type' => 'iron sheets',
                'house_fence_type' => 'block wall',
            ]
        );

        MemberNominee::updateOrCreate(
            ['member_id' => $member->id, 'name' => 'Family Nominee'],
            ['relationship' => 'Spouse', 'percentage' => 100, 'attested_at' => now()->subMonths(8)]
        );

        MemberSecurityAccount::updateOrCreate(
            ['member_id' => $member->id],
            ['balance' => $data['membership_number'] === 'VATI-M-2026-000001' ? 120000 : 0]
        );

        return $member;
    }

    private function applicationDetails(LoanApplication $application, Member $applicant, $witnesses, User $loanOfficer, User $creditOfficer, User $manager): void
    {
        LoanUtilization::where('loan_application_id', $application->id)->delete();
        foreach ([
            ['purpose' => 'Wholesale grocery stock', 'allocation_amount' => 850000, 'current_asset_value' => 600000],
            ['purpose' => 'Cold-storage freezer deposit', 'allocation_amount' => 350000, 'current_asset_value' => 0],
        ] as $utilization) {
            LoanUtilization::create(['loan_application_id' => $application->id] + $utilization);
        }

        $application->assessment()->updateOrCreate(
            ['loan_application_id' => $application->id],
            [
                'core_business_income' => 1350000,
                'other_income' => 120000,
                'business_expenses' => 610000,
                'household_expenses' => 280000,
                'monthly_profit' => 740000,
                'disposable_income' => 460000,
                'existing_external_debt' => 0,
                'debt_service_ratio' => 29.5,
                'affordability_score' => 82,
                'assessment_comment' => 'Stable daily trade, adequate disposable income, and active group support.',
            ]
        );

        LoanGuarantor::updateOrCreate(
            ['loan_application_id' => $application->id, 'name' => 'Juma Mwakalinga'],
            [
                'guarantor_type' => 'spouse',
                'relationship' => 'Spouse',
                'phone' => '+255715009901',
                'national_id' => '19830410123456789012',
                'house_number' => 'MCK-44',
                'street' => 'Mikocheni Street',
                'ward' => 'Mikocheni',
                'district' => 'Kinondoni',
                'region' => 'Dar es Salaam',
                'business_address' => 'Mikocheni Market',
                'declaration_text' => 'I accept guarantor responsibility for the approved facility.',
                'declaration_accepted_at' => now()->subDays(15),
            ]
        );

        foreach ($witnesses as $witness) {
            LoanGroupWitness::updateOrCreate(
                ['loan_application_id' => $application->id, 'member_id' => $witness->id],
                [
                    'group_id' => $application->group_id,
                    'confirmed_at' => now()->subDays(15),
                    'recorded_by' => $loanOfficer->id,
                ]
            );
        }

        foreach ([
            ['document_type' => 'national_id', 'file_path' => 'demo/national-id-asha.pdf', 'original_name' => 'national-id-asha.pdf'],
            ['document_type' => 'business_photo', 'file_path' => 'demo/business-photo-asha.jpg', 'original_name' => 'business-photo-asha.jpg'],
            ['document_type' => 'group_minutes', 'file_path' => 'demo/group-minutes-mikocheni.pdf', 'original_name' => 'group-minutes-mikocheni.pdf'],
        ] as $document) {
            LoanDocument::updateOrCreate(
                ['loan_application_id' => $application->id, 'document_type' => $document['document_type']],
                $document + [
                    'is_required' => true,
                    'verification_status' => 'verified',
                    'verification_remarks' => 'Verified for demo workflow.',
                    'uploaded_by' => $loanOfficer->id,
                    'verified_by' => $creditOfficer->id,
                    'verified_at' => now()->subDays(14),
                    'mime_type' => Str::endsWith($document['file_path'], '.jpg') ? 'image/jpeg' : 'application/pdf',
                    'size_bytes' => 245760,
                    'remarks' => 'Seeded document metadata.',
                ]
            );
        }

        CreditReview::updateOrCreate(
            ['loan_application_id' => $application->id, 'attempt' => 1],
            [
                'decision' => 'recommended',
                'recommended_amount' => 1200000,
                'recommended_duration_months' => 6,
                'overall_risk' => 'low',
                'remarks' => 'Recommended after KYC, group, and document verification.',
                'member_verified' => true,
                'group_membership_verified' => true,
                'documents_verified' => true,
                'reviewed_by' => $creditOfficer->id,
                'reviewed_at' => now()->subDays(13),
            ]
        );

        foreach ([
            ['user_id' => $loanOfficer->id, 'role' => 'loan_officer', 'decision' => 'submitted', 'from_status' => 'draft', 'to_status' => 'lo_review', 'remarks' => 'Application received and KYC checked.', 'acted_at' => now()->subDays(16)],
            ['user_id' => $creditOfficer->id, 'role' => 'credit_officer', 'decision' => 'recommended', 'from_status' => 'credit_review', 'to_status' => 'recommended', 'remarks' => 'Credit review recommended.', 'acted_at' => now()->subDays(13)],
            ['user_id' => $manager->id, 'role' => 'branch_manager', 'decision' => 'approved', 'from_status' => 'recommended', 'to_status' => 'approved', 'remarks' => 'Approved for disbursement.', 'acted_at' => now()->subDays(12)],
        ] as $approval) {
            LoanApproval::updateOrCreate(
                ['loan_application_id' => $application->id, 'role' => $approval['role']],
                $approval + ['loan_application_id' => $application->id]
            );
        }
    }

    private function groupActivity(MemberGroup $group, $members, User $loanOfficer): void
    {
        $meeting = GroupMeeting::updateOrCreate(
            ['group_id' => $group->id, 'meeting_date' => now()->subWeek()->toDateString()],
            ['loan_officer_id' => $loanOfficer->id, 'status' => 'completed', 'notes' => 'Weekly repayment and loan review meeting.']
        );

        foreach ($members as $member) {
            GroupAttendance::updateOrCreate(
                ['group_meeting_id' => $meeting->id, 'member_id' => $member->id],
                ['status' => 'present']
            );
        }

        GroupCollection::updateOrCreate(
            ['group_id' => $group->id, 'collection_date' => now()->subWeek()->toDateString()],
            [
                'group_meeting_id' => $meeting->id,
                'expected_amount' => 276924,
                'collected_amount' => 138462,
                'outstanding_amount' => 138462,
                'loan_officer_id' => $loanOfficer->id,
                'status' => 'open',
            ]
        );
    }

    private function loan(LoanApplication $application, LoanProduct $product, Member $member, MemberGroup $group, Branch $branch, User $manager, User $cashier): void
    {
        $figures = app(LoanCalculatorService::class)->calculate($product, 1200000, 6);
        $paidAmount = 138462;
        $loan = Loan::updateOrCreate(
            ['loan_number' => 'VATI-LN-2026-000001'],
            [
                'loan_application_id' => $application->id,
                'member_id' => $member->id,
                'group_id' => $group->id,
                'loan_product_id' => $product->id,
                'branch_id' => $branch->id,
                'principal_amount' => $figures['principal'],
                'interest_amount' => $figures['interest'],
                'total_repayment' => $figures['total_repayment'],
                'principal_balance' => $figures['principal'] - 130435,
                'interest_balance' => $figures['interest'] - 8027,
                'total_balance' => $figures['total_repayment'] - $paidAmount,
                'number_of_installments' => 26,
                'installment_amount' => round($figures['total_repayment'] / 26, 2),
                'disbursement_date' => now()->subDays(10)->toDateString(),
                'first_payment_date' => now()->subDays(3)->toDateString(),
                'maturity_date' => now()->subDays(3)->addWeeks(25)->toDateString(),
                'status' => 'active',
            ]
        );

        PaymentAllocation::whereHas('payment', fn ($query) => $query->where('loan_id', $loan->id))->delete();
        Payment::where('loan_id', $loan->id)->delete();
        LoanInstallment::where('loan_id', $loan->id)->delete();

        $principalPerInstallment = round($figures['principal'] / 26, 2);
        $interestPerInstallment = round($figures['interest'] / 26, 2);
        $balance = $figures['total_repayment'];

        for ($number = 1; $number <= 26; $number++) {
            $principal = $number === 26 ? round($figures['principal'] - ($principalPerInstallment * 25), 2) : $principalPerInstallment;
            $interest = $number === 26 ? round($figures['interest'] - ($interestPerInstallment * 25), 2) : $interestPerInstallment;
            $total = round($principal + $interest, 2);
            $status = $number === 1 ? 'paid' : ($number === 2 ? 'due' : 'upcoming');

            LoanInstallment::create([
                'loan_id' => $loan->id,
                'installment_number' => $number,
                'due_date' => now()->subDays(3)->addWeeks($number - 1)->toDateString(),
                'principal_due' => $principal,
                'interest_due' => $interest,
                'total_due' => $total,
                'principal_paid' => $number === 1 ? $principal : 0,
                'interest_paid' => $number === 1 ? $interest : 0,
                'total_paid' => $number === 1 ? $total : 0,
                'outstanding_balance' => max(0, round($balance - ($number === 1 ? $total : 0), 2)),
                'status' => $status,
            ]);

            $balance = round($balance - $total, 2);
        }

        LoanDisbursement::updateOrCreate(
            ['loan_id' => $loan->id],
            [
                'amount' => $figures['principal'],
                'method' => 'mobile_money',
                'recipient_number' => $member->phone,
                'reference_number' => 'DISB-DEMO-000001',
                'provider_reference' => 'MPESA-DEMO-000001',
                'disbursed_at' => now()->subDays(10),
                'processed_by' => $cashier->id,
                'approved_by' => $manager->id,
                'status' => 'completed',
            ]
        );

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'idempotency_key' => 'demo-payment-vati-000001',
            'payment_number' => 'VATI-PAY-2026-000001',
            'member_id' => $member->id,
            'loan_id' => $loan->id,
            'branch_id' => $branch->id,
            'amount' => $paidAmount,
            'payment_method' => 'cash',
            'reference_number' => 'RCPT-DEMO-000001',
            'paid_at' => now()->subDays(2),
            'collected_by' => $cashier->id,
            'sync_status' => 'synced',
            'remarks' => 'Seeded first installment payment.',
            'status' => 'posted',
        ]);

        $firstInstallment = $loan->installments()->where('installment_number', 1)->first();
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'loan_installment_id' => $firstInstallment?->id,
            'principal_amount' => 130435,
            'interest_amount' => 8027,
            'penalty_amount' => 0,
        ]);

        SecurityTransaction::updateOrCreate(
            ['transaction_number' => 'VATI-SEC-2026-000001'],
            [
                'member_security_account_id' => $member->securityAccount->id,
                'loan_id' => $loan->id,
                'transaction_type' => 'deposit',
                'amount' => $figures['security_amount'],
                'balance_before' => 0,
                'balance_after' => $figures['security_amount'],
                'remarks' => 'Security deposit for seeded loan.',
                'created_by' => $cashier->id,
                'transaction_date' => now()->subDays(10),
            ]
        );
    }
}
