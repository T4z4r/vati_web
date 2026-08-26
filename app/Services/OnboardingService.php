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
use Illuminate\Support\Facades\Storage;
use Throwable;

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
        $photo = Arr::pull($data, 'photo');
        $photoPath = $photo?->store('members/photos', 'public');
        if ($photoPath) {
            $data['photo_path'] = $photoPath;
        }

        try {
            return DB::transaction(function () use ($data, $user) {
                $kyc = Arr::pull($data, 'kyc');
                $nominees = Arr::pull($data, 'nominees', []);
                $familyMembers = Arr::pull($data, 'family_members', []);
                $assets = Arr::pull($data, 'assets', []);
                $group = MemberGroup::query()->lockForUpdate()->findOrFail($data['group_id']);
                $this->assertMemberBranchAccess($user, (int) $data['branch_id']);
                if (! $group->status || (int) $group->branch_id !== (int) $data['branch_id']) {
                    throw new DomainException('Member onboarding requires an active group in the selected branch.');
                }

                $member = Member::create([...$data, 'membership_number' => $this->numbers->member(), 'created_by' => $user->id]);
                if ($kyc) {
                    $member->kyc()->create($kyc);
                }
                $this->memberships->assign($member, $group, $member->admission_date ?? today());
                $this->replaceMemberCollections($member, $nominees, $familyMembers, $assets);
                activity()->causedBy($user)->performedOn($member)->withProperties(['group_id' => $group->id])->log('Member onboarded');

                return $this->loadMember($member);
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }
    }

    public function updateMember(Member $member, array $data, User $user): Member
    {
        $replaceNominees = array_key_exists('nominees', $data);
        $replaceFamily = array_key_exists('family_members', $data);
        $replaceAssets = array_key_exists('assets', $data);
        $kycProvided = array_key_exists('kyc', $data);
        $kyc = Arr::pull($data, 'kyc');
        $nominees = Arr::pull($data, 'nominees', []);
        $familyMembers = Arr::pull($data, 'family_members', []);
        $assets = Arr::pull($data, 'assets', []);
        $photo = Arr::pull($data, 'photo');
        $newPhotoPath = $photo?->store('members/photos', 'public');
        $oldPhotoPath = $member->photo_path;
        if ($newPhotoPath) {
            $data['photo_path'] = $newPhotoPath;
        }

        try {
            $member = DB::transaction(function () use ($member, $data, $user, $kycProvided, $kyc, $replaceNominees, $nominees, $replaceFamily, $familyMembers, $replaceAssets, $assets) {
                $member = Member::query()->lockForUpdate()->findOrFail($member->id);
                $branchId = (int) ($data['branch_id'] ?? $member->branch_id);
                $groupId = (int) ($data['group_id'] ?? $member->group_id);
                $this->assertMemberBranchAccess($user, $branchId);
                $group = MemberGroup::query()->lockForUpdate()->findOrFail($groupId);
                if (! $group->status || (int) $group->branch_id !== $branchId) {
                    throw new DomainException('The selected group must be active and belong to the selected branch.');
                }

                $groupChanged = (int) $member->group_id !== $groupId;
                $member->update([...$data, 'branch_id' => $branchId, 'group_id' => $groupId]);
                if ($kycProvided) {
                    $member->kyc()->updateOrCreate(['member_id' => $member->id], $kyc ?? []);
                }
                if ($groupChanged) {
                    $this->memberships->assign($member, $group, $member->admission_date ?? today());
                }
                if ($replaceNominees) {
                    $member->nominees()->delete();
                    $this->createNominees($member, $nominees);
                }
                if ($replaceFamily) {
                    $member->familyMembers()->delete();
                    $this->createFamilyMembers($member, $familyMembers);
                }
                if ($replaceAssets) {
                    $member->assets()->delete();
                    $this->createAssets($member, $assets);
                }
                activity()->causedBy($user)->performedOn($member)->log('Member profile updated');

                return $this->loadMember($member->refresh());
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return $member;
    }

    public function loanApplication(array $data, User $user): LoanApplication
    {
        return DB::transaction(function () use ($data, $user) {
            $assessment = Arr::pull($data, 'assessment', []);
            $utilizations = Arr::pull($data, 'utilizations', []);
            $guarantors = Arr::pull($data, 'guarantors', []);
            $witnessMemberIds = Arr::pull($data, 'witness_member_ids', []);
            $member = Member::with(['group', 'activeGroupMembership'])->lockForUpdate()->findOrFail($data['member_id']);
            $product = LoanProduct::query()->lockForUpdate()->findOrFail($data['loan_product_id']);

            if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $member->branch_id !== $user->branch_id) {
                abort(403, 'You cannot onboard an application for another branch.');
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
            $witnesses = $this->eligibleWitnesses($member, $witnessMemberIds);

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
                'status' => 'submitted',
                'submitted_at' => now(),
                'created_by' => $user->id,
                'calc_interest' => $figures['interest'],
                'calc_processing_fee' => $figures['processing_fee'],
                'calc_insurance_fee' => $figures['insurance_fee'],
                'calc_vat' => $figures['vat'],
                'calc_security_amount' => $figures['security_amount'],
                'calc_charges' => $figures['charges'],
                'calc_amount_receivable' => $figures['amount_receivable'],
                'calc_total_repayment' => $figures['total_repayment'],
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
            foreach ($guarantors as $guarantor) {
                unset($guarantor['id']);
                $application->guarantors()->create($guarantor);
            }
            foreach ($witnesses as $witness) {
                $application->groupWitnesses()->create([
                    'group_id' => $member->group_id,
                    'member_id' => $witness->id,
                    'confirmed_at' => now(),
                    'recorded_by' => $user->id,
                ]);
            }

            activity()->causedBy($user)->performedOn($application)->withProperties(['member_id' => $member->id, 'group_id' => $member->group_id])->log('Loan application onboarded');

            return $application->load('member.nominees', 'product', 'group', 'branch', 'assessment', 'utilizations', 'guarantors', 'groupWitnesses.member');
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

            $assessment = Arr::pull($data, 'assessment', []);
            $utilizations = Arr::pull($data, 'utilizations', []);
            $replaceGuarantors = array_key_exists('guarantors', $data);
            $guarantors = Arr::pull($data, 'guarantors', []);
            $replaceWitnesses = array_key_exists('witness_member_ids', $data);
            $witnessMemberIds = Arr::pull($data, 'witness_member_ids', []);
            $member = Member::with(['group', 'activeGroupMembership'])->lockForUpdate()->findOrFail($data['member_id']);
            $product = LoanProduct::query()->lockForUpdate()->findOrFail($data['loan_product_id']);
            if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $member->branch_id !== $user->branch_id) {
                abort(403, 'You cannot edit an application for another branch.');
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
            $witnesses = $this->eligibleWitnesses($member, $witnessMemberIds);

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
            if ($replaceGuarantors) {
                $this->syncGuarantors($application, $guarantors);
            }
            if ($replaceWitnesses) {
                $this->syncWitnesses($application, $member, $witnesses, $user);
            }
            activity()->causedBy($user)->performedOn($application)->log('Draft loan application edited');

            return $application->refresh()->load('member.nominees', 'product', 'group', 'branch', 'assessment', 'utilizations', 'guarantors', 'groupWitnesses.member');
        });
    }

    private function eligibleWitnesses(Member $applicant, array $memberIds)
    {
        if ($memberIds === []) {
            return collect();
        }

        $witnesses = Member::with('activeGroupMembership')->whereIn('id', $memberIds)->get();
        if ($witnesses->count() !== count(array_unique($memberIds)) || $witnesses->contains(fn (Member $member) => $member->id === $applicant->id
            || $member->status !== 'active'
            || (int) $member->group_id !== (int) $applicant->group_id
            || (int) $member->activeGroupMembership?->group_id !== (int) $applicant->group_id
        )) {
            throw new DomainException('Every group witness must be another active member of the applicant’s group.');
        }

        return $witnesses;
    }

    private function assertMemberBranchAccess(User $user, int $branchId): void
    {
        if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && (int) $user->branch_id !== $branchId) {
            abort(403, 'You cannot manage a member in another branch.');
        }
    }

    private function replaceMemberCollections(Member $member, array $nominees, array $familyMembers, array $assets): void
    {
        $this->createNominees($member, $nominees);
        $this->createFamilyMembers($member, $familyMembers);
        $this->createAssets($member, $assets);
    }

    private function createNominees(Member $member, array $nominees): void
    {
        foreach ($nominees as $nominee) {
            $member->nominees()->create([...$nominee, 'attested_at' => now()]);
        }
    }

    private function createFamilyMembers(Member $member, array $familyMembers): void
    {
        foreach ($familyMembers as $familyMember) {
            $member->familyMembers()->create($familyMember);
        }
    }

    private function createAssets(Member $member, array $assets): void
    {
        foreach ($assets as $asset) {
            $name = Arr::pull($asset, 'name');
            $category = Arr::pull($asset, 'category');
            $assetType = AssetType::firstOrCreate(['name' => $name], ['category' => $category, 'status' => true]);
            $member->assets()->create([...$asset, 'asset_type_id' => $assetType->id]);
        }
    }

    private function loadMember(Member $member): Member
    {
        return $member->load('branch.manager', 'group.loanOfficer', 'createdBy', 'kyc', 'activeGroupMembership', 'nominees', 'familyMembers', 'assets.assetType', 'documents.uploadedBy', 'securityAccount.transactions', 'passbookReplacements');
    }

    private function syncGuarantors(LoanApplication $application, array $guarantors): void
    {
        $existing = $application->guarantors()->get();
        $keptIds = [];
        foreach ($guarantors as $guarantor) {
            $id = Arr::pull($guarantor, 'id');
            $record = $id ? $existing->firstWhere('id', (int) $id) : null;
            if ($record) {
                $record->update($guarantor);
            } else {
                $record = $application->guarantors()->create($guarantor);
            }
            $keptIds[] = $record->id;
        }
        $application->guarantors()->when($keptIds, fn ($query) => $query->whereNotIn('id', $keptIds))->delete();
    }

    private function syncWitnesses(LoanApplication $application, Member $applicant, $witnesses, User $user): void
    {
        $memberIds = $witnesses->pluck('id')->all();
        $application->groupWitnesses()->when($memberIds, fn ($query) => $query->whereNotIn('member_id', $memberIds))->delete();
        foreach ($witnesses as $witness) {
            $application->groupWitnesses()->firstOrCreate(
                ['member_id' => $witness->id],
                ['group_id' => $applicant->group_id, 'confirmed_at' => now(), 'recorded_by' => $user->id]
            );
        }
    }
}
