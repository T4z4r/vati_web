<?php

namespace App\Http\Controllers\Web;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardLoanApplicationRequest;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\ApplicationComplianceService;
use App\Services\LoanCalculatorService;
use App\Services\LoanApprovalService;
use App\Services\OnboardingService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = LoanApplication::with(['member', 'product', 'group', 'loan'])->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('application_number', 'like', "%{$v}%")->orWhereHas('member', fn ($m) => $m->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%"))))->latest()->paginate(20)->withQueryString();

        return view('admin.loan-applications.index', compact('applications'));
    }

    public function create(Request $request)
    {
        return view('admin.loan-applications.form', $this->formData($request, new LoanApplication, $request->integer('member_id')));
    }

    public function store(OnboardLoanApplicationRequest $request, OnboardingService $service)
    {
        try {
            $application = $service->loanApplication($request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.loan-applications.show', $application)->with('success', 'Loan application created.');
    }

    public function edit(Request $request, LoanApplication $loanApplication)
    {
        abort_unless($loanApplication->status === ApplicationStatus::DRAFT, 409, 'Only draft applications can be edited.');
        $loanApplication->load('assessment', 'utilizations');

        return view('admin.loan-applications.form', $this->formData($request, $loanApplication, $loanApplication->member_id));
    }

    public function update(OnboardLoanApplicationRequest $request, LoanApplication $loanApplication, OnboardingService $service)
    {
        try {
            $service->updateLoanApplication($loanApplication, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.loan-applications.show', $loanApplication)->with('success', 'Draft loan application updated.');
    }

    public function show(LoanApplication $loanApplication, LoanCalculatorService $calculator)
    {
        $loanApplication->load([
            'member.kyc', 'member.nominees', 'member.familyMembers', 'member.assets.assetType',
            'member.branch.area.region', 'member.group.loanOfficer', 'member.activeGroupMembership',
            'product', 'group.loanOfficer', 'branch.area.region', 'assessment', 'utilizations',
            'approvals.user', 'groupWitnesses.member', 'loan.disbursement', 'term', 'guarantors',
            'documents.uploader', 'documents.verifier', 'cancellation', 'assignedCreditOfficer', 'latestCreditReview.reviewer',
        ]);
        $used = $loanApplication->groupWitnesses->pluck('member_id')->push($loanApplication->member_id);
        $eligible = $loanApplication->group->members()->where('status', 'active')->whereNotIn('id', $used)->whereHas('activeGroupMembership', fn ($q) => $q->where('group_id', $loanApplication->group_id))->orderBy('first_name')->get();
        $figures = $calculator->calculate($loanApplication->product, (float) $loanApplication->requested_amount, (int) $loanApplication->duration_months);
        $installmentCount = $loanApplication->product->repayment_frequency === 'weekly'
            ? max(1, (int) round($loanApplication->duration_months * 52 / 12))
            : $loanApplication->duration_months;

        return view('admin.loan-applications.show', [
            'application' => $loanApplication,
            'eligibleWitnesses' => $eligible,
            'figures' => $figures,
            'installmentCount' => $installmentCount,
        ]);
    }

    public function submit(LoanApplication $loanApplication, ApplicationComplianceService $compliance)
    {
        if ($loanApplication->status !== ApplicationStatus::DRAFT) {
            return back()->with('error', 'Only draft applications can be submitted.');
        }
        try {
            $compliance->assertReadyForSubmission($loanApplication);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        $loanApplication->update(['status' => ApplicationStatus::SUBMITTED, 'submitted_at' => now()]);

        return back()->with('success', 'Application submitted for review.');
    }

    public function witness(Request $request, LoanApplication $loanApplication)
    {
        $data = $request->validate(['member_id' => ['required', 'exists:members,id'], 'signature_path' => ['nullable', 'max:2048']]);
        try {
            DB::transaction(function () use ($loanApplication, $data, $request) {
                $app = LoanApplication::lockForUpdate()->findOrFail($loanApplication->id);
                if ($app->groupWitnesses()->where('member_id', $data['member_id'])->exists()) {
                    throw new DomainException('This member has already confirmed the application.');
                }$member = Member::with('activeGroupMembership')->findOrFail($data['member_id']);
                if ($member->id === $app->member_id) {
                    throw new DomainException('The borrower cannot witness their own application.');
                }if ($member->status !== 'active' || $member->group_id !== $app->group_id || $member->activeGroupMembership?->group_id !== $app->group_id) {
                    throw new DomainException('Witness must be an active member of the same group.');
                }$app->groupWitnesses()->create(['group_id' => $app->group_id, 'member_id' => $member->id, 'signature_path' => $data['signature_path'] ?? null, 'confirmed_at' => now(), 'recorded_by' => $request->user()->id]);
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Group witness confirmed.');
    }

    public function approve(Request $request, LoanApplication $loanApplication, LoanApprovalService $service)
    {
        try {
            $service->decide($loanApplication, $request->user(), 'approved', $request->input('remarks'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Application approved and loan account created.');
    }

    public function reject(Request $request, LoanApplication $loanApplication, LoanApprovalService $service)
    {
        $request->validate(['remarks' => ['required', 'min:5']]);
        try {
            $service->decide($loanApplication, $request->user(), 'rejected', $request->remarks);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Application rejected.');
    }

    public function destroy(LoanApplication $loanApplication)
    {
        if ($loanApplication->loan()->exists()) {
            return back()->with('error', 'This application already has a loan account and cannot be deleted.');
        }

        if (! in_array($loanApplication->status, [ApplicationStatus::DRAFT, ApplicationStatus::REJECTED, ApplicationStatus::CANCELLED], true)) {
            return back()->with('error', 'Only draft, rejected, or cancelled applications can be deleted.');
        }

        $loanApplication->delete();

        return redirect()->route('admin.loan-applications.index')->with('success', 'Loan application deleted.');
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }

    private function formData(Request $request, LoanApplication $application, int $selectedMember): array
    {
        $members = Member::with([
            'branch.area.region', 'group.loanOfficer', 'kyc', 'activeGroupMembership',
            'familyMembers', 'assets.assetType', 'nominees',
            'loans' => fn ($query) => $query->whereIn('status', ['pending_disbursement', 'active', 'overdue'])->latest(),
        ])->where('status', 'active')->whereHas('activeGroupMembership')->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->orderBy('first_name')->get();

        return [
            'application' => $application,
            'members' => $members,
            'memberProfiles' => $members->mapWithKeys(fn (Member $member) => [$member->id => [
                'membership_number' => $member->membership_number,
                'full_name' => trim("{$member->first_name} {$member->middle_name} {$member->last_name}"),
                'initials' => strtoupper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)),
                'photo_url' => $member->photo_path ? asset('storage/'.$member->photo_path) : null,
                'guardian_name' => $member->guardian_name,
                'occupation' => $member->occupation,
                'date_of_birth' => $member->date_of_birth?->toDateString(),
                'age' => $member->date_of_birth?->age,
                'gender' => $member->gender,
                'marital_status' => $member->marital_status,
                'nationality' => $member->nationality,
                'phone' => $member->phone,
                'alternate_phone' => $member->alternate_phone,
                'national_id' => $member->national_id,
                'voter_id' => $member->voter_id,
                'physical_address' => $member->physical_address,
                'region' => $member->region,
                'district' => $member->district,
                'ward' => $member->ward,
                'street' => $member->street,
                'branch' => $member->branch?->branch_name,
                'area' => $member->branch?->area?->name,
                'organization_region' => $member->branch?->area?->region?->name,
                'group' => $member->group?->group_name,
                'meeting_day' => $member->group?->meeting_day,
                'group_location' => $member->group?->location,
                'loan_officer' => $member->group?->loanOfficer?->name,
                'mpesa_phone' => $member->kyc?->mpesa_phone,
                'bank_account_number' => $member->kyc?->bank_account_number,
                'bank_account_name' => $member->kyc?->bank_account_name,
                'bank_name' => $member->kyc?->bank_name,
                'house_number' => $member->kyc?->house_number,
                'police_station' => $member->kyc?->police_station,
                'business_name' => $member->kyc?->business_name,
                'business_type' => $member->kyc?->business_type,
                'business_address' => $member->kyc?->business_address,
                'household_monthly_income' => $member->kyc?->household_monthly_income,
                'household_monthly_expenses' => $member->kyc?->household_monthly_expenses,
                'number_of_dependants' => $member->kyc?->number_of_dependants,
                'head_of_household' => $member->kyc?->head_of_household,
                'house_ownership_status' => $member->kyc?->house_ownership_status,
                'house_roof_type' => $member->kyc?->house_roof_type,
                'house_fence_type' => $member->kyc?->house_fence_type,
                'current_loan_balance' => $member->loans->sum('total_balance'),
                'family_members_count' => $member->familyMembers->count(),
                'assets_count' => $member->assets->count(),
                'nominees_count' => $member->nominees->count(),
            ]]),
            'products' => LoanProduct::where('status', true)->orderBy('name')->get(),
            'selectedMember' => $selectedMember,
        ];
    }
}
