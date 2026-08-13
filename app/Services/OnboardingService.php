<?php

namespace App\Services;

use App\Models\AssetType;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function __construct(private NumberGeneratorService $numbers, private GroupMembershipService $memberships, private LoanCalculatorService $calculator) {}

    public function group(array $data, User $user): MemberGroup
    {
        return DB::transaction(function () use ($data, $user) {
            $group = MemberGroup::create([...$data, 'group_code' => $this->numbers->group()]);
            activity()->causedBy($user)->performedOn($group)->log('Group onboarded');

            return $group->load('branch', 'loanOfficer');
        });
    }

    public function member(array $data, User $user): Member
    {
        return DB::transaction(function () use ($data, $user) {
            $kyc = Arr::pull($data, 'kyc');
            $nominees = Arr::pull($data, 'nominees', []);
            $familyMembers = Arr::pull($data, 'family_members', []);
            $assets = Arr::pull($data, 'assets', []);
            $group = MemberGroup::query()->lockForUpdate()->findOrFail($data['group_id']);
            if (! $group->status || (int) $group->branch_id !== (int) $data['branch_id']) {
                throw new DomainException('Member onboarding requires an active group in the selected branch.');
            }

            $member = Member::create([...$data, 'membership_number' => $this->numbers->member(), 'created_by' => $user->id]);
            if ($kyc) {
                $member->kyc()->create($kyc);
            }
            $this->memberships->assign($member, $group, $member->admission_date ?? today());
            foreach ($nominees as $nominee) {
                $member->nominees()->create([...$nominee, 'attested_at' => now()]);
            }
            foreach ($familyMembers as $familyMember) {
                $member->familyMembers()->create($familyMember);
            }
            foreach ($assets as $asset) {
                $name = Arr::pull($asset, 'name');
                $category = Arr::pull($asset, 'category');
                $assetType = AssetType::firstOrCreate(['name' => $name], ['category' => $category, 'status' => true]);
                $member->assets()->create([...$asset, 'asset_type_id' => $assetType->id]);
            }
            activity()->causedBy($user)->performedOn($member)->withProperties(['group_id' => $group->id])->log('Member onboarded');

            return $member->load('branch.manager', 'group.loanOfficer', 'createdBy', 'kyc', 'activeGroupMembership', 'nominees', 'familyMembers', 'assets.assetType', 'documents.uploadedBy', 'securityAccount.transactions', 'passbookReplacements');
        });
    }

    public function loanApplication(array $data, User $user): LoanApplication
    {
        return DB::transaction(function () use ($data, $user) {
            $assessment = Arr::pull($data, 'assessment');
            $utilizations = Arr::pull($data, 'utilizations', []);
            $member = Member::with(['group', 'activeGroupMembership'])->lockForUpdate()->findOrFail($data['member_id']);
            $product = LoanProduct::query()->lockForUpdate()->findOrFail($data['loan_product_id']);

            if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $member->branch_id !== $user->branch_id) {
                throw new DomainException('You cannot onboard an application for another branch.');
            }
            if ($member->status !== 'active' || ! $member->group?->status || $member->activeGroupMembership?->group_id !== $member->group_id) {
                throw new DomainException('Loan application onboarding requires an active member with a matching active group membership.');
            }
            if (! $product->status) {
                throw new DomainException('Loan application onboarding requires an active loan product.');
            }
            if ($member->loanApplications()->whereNotIn('status', ['rejected', 'cancelled', 'disbursed'])->exists()) {
                throw new DomainException('The member already has an open loan application.');
            }
            $hasCurrentLoan = $member->loans()->whereIn('status', ['pending_disbursement', 'active', 'overdue'])->exists();
            if ($data['application_type'] === 'main' && $hasCurrentLoan) {
                throw new DomainException('A member with a current loan must use the refinance or top-up application type.');
            }
            if (in_array($data['application_type'], ['refinance', 'top_up'], true) && ! $hasCurrentLoan) {
                throw new DomainException('Refinance and top-up applications require a current loan.');
            }

            $figures = $this->calculator->calculate($product, (float) $data['requested_amount'], (int) $data['duration_months']);
            $income = (float) ($assessment['core_business_income'] ?? 0) + (float) ($assessment['other_income'] ?? 0);
            $expenses = (float) ($assessment['business_expenses'] ?? 0) + (float) ($assessment['household_expenses'] ?? 0);
            $disposable = $income - $expenses;
            $monthlyRepayment = (float) $figures['total_repayment'] / max(1, (int) $data['duration_months']);

            $application = LoanApplication::create([
                ...$data,
                'application_number' => $this->numbers->application(),
                'group_id' => $member->group_id,
                'branch_id' => $member->branch_id,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            $application->assessment()->create([
                ...$assessment,
                'monthly_profit' => $income - (float) ($assessment['business_expenses'] ?? 0),
                'disposable_income' => $disposable,
                'debt_service_ratio' => $disposable > 0 ? round($monthlyRepayment / $disposable * 100, 4) : null,
                'affordability_score' => $monthlyRepayment > 0 ? round(max(0, $disposable) / $monthlyRepayment * 100, 4) : null,
            ]);
            foreach ($utilizations as $utilization) {
                $application->utilizations()->create($utilization);
            }

            activity()->causedBy($user)->performedOn($application)->withProperties(['member_id' => $member->id, 'group_id' => $member->group_id])->log('Loan application onboarded');

            return $application->load('member.nominees', 'product', 'group', 'branch', 'assessment', 'utilizations');
        });
    }

    public function updateLoanApplication(LoanApplication $application, array $data, User $user): LoanApplication
    {
        return DB::transaction(function () use ($application, $data, $user) {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($application->status->value !== 'draft') {
                throw new DomainException('Only draft loan applications can be edited.');
            }
            if ((int) $data['member_id'] !== (int) $application->member_id) {
                throw new DomainException('The applicant cannot be changed on an existing draft. Create a new application instead.');
            }

            $assessment = Arr::pull($data, 'assessment');
            $utilizations = Arr::pull($data, 'utilizations', []);
            $member = Member::with(['group', 'activeGroupMembership'])->lockForUpdate()->findOrFail($data['member_id']);
            $product = LoanProduct::query()->lockForUpdate()->findOrFail($data['loan_product_id']);
            if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $member->branch_id !== $user->branch_id) {
                throw new DomainException('You cannot edit an application for another branch.');
            }
            if ($member->status !== 'active' || ! $member->group?->status || $member->activeGroupMembership?->group_id !== $member->group_id) {
                throw new DomainException('A draft application requires an active member with a matching active group membership.');
            }
            if (! $product->status) {
                throw new DomainException('The selected loan product is inactive.');
            }
            if ($member->loanApplications()->where('id', '!=', $application->id)->whereNotIn('status', ['rejected', 'cancelled', 'disbursed'])->exists()) {
                throw new DomainException('The member already has another open loan application.');
            }
            $hasCurrentLoan = $member->loans()->whereIn('status', ['pending_disbursement', 'active', 'overdue'])->exists();
            if ($data['application_type'] === 'main' && $hasCurrentLoan) {
                throw new DomainException('A member with a current loan must use the refinance or top-up application type.');
            }
            if (in_array($data['application_type'], ['refinance', 'top_up'], true) && ! $hasCurrentLoan) {
                throw new DomainException('Refinance and top-up applications require a current loan.');
            }

            $figures = $this->calculator->calculate($product, (float) $data['requested_amount'], (int) $data['duration_months']);
            $income = (float) ($assessment['core_business_income'] ?? 0) + (float) ($assessment['other_income'] ?? 0);
            $expenses = (float) ($assessment['business_expenses'] ?? 0) + (float) ($assessment['household_expenses'] ?? 0);
            $disposable = $income - $expenses;
            $monthlyRepayment = (float) $figures['total_repayment'] / max(1, (int) $data['duration_months']);

            $application->update([
                ...$data,
                'group_id' => $member->group_id,
                'branch_id' => $member->branch_id,
                'loan_term_id' => null,
                'consent_declaration' => null,
                'consented_at' => null,
                'consented_ip' => null,
                'cancellation_deadline' => null,
                'applicant_signature_path' => null,
                'applicant_thumbprint_path' => null,
            ]);
            $application->assessment()->updateOrCreate(['loan_application_id' => $application->id], [
                ...$assessment,
                'monthly_profit' => $income - (float) ($assessment['business_expenses'] ?? 0),
                'disposable_income' => $disposable,
                'debt_service_ratio' => $disposable > 0 ? round($monthlyRepayment / $disposable * 100, 4) : null,
                'affordability_score' => $monthlyRepayment > 0 ? round(max(0, $disposable) / $monthlyRepayment * 100, 4) : null,
            ]);
            $application->utilizations()->delete();
            foreach ($utilizations as $utilization) {
                $application->utilizations()->create($utilization);
            }
            activity()->causedBy($user)->performedOn($application)->log('Draft loan application edited');

            return $application->refresh()->load('member.nominees', 'product', 'group', 'branch', 'assessment', 'utilizations');
        });
    }
}
