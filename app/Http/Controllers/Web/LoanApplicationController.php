<?php

namespace App\Http\Controllers\Web;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardLoanApplicationRequest;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\ApplicationComplianceService;
use App\Services\LoanApprovalService;
use App\Services\OnboardingService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = LoanApplication::with(['member', 'product', 'group'])->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('application_number', 'like', "%{$v}%")->orWhereHas('member', fn ($m) => $m->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%"))))->latest()->paginate(20)->withQueryString();

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

    public function show(LoanApplication $loanApplication)
    {
        $loanApplication->load(['member.kyc', 'member.nominees', 'product', 'group', 'assessment', 'approvals.user', 'groupWitnesses.member', 'loan', 'term', 'guarantors', 'documents', 'cancellation']);
        $used = $loanApplication->groupWitnesses->pluck('member_id')->push($loanApplication->member_id);
        $eligible = $loanApplication->group->members()->where('status', 'active')->whereNotIn('id', $used)->whereHas('activeGroupMembership', fn ($q) => $q->where('group_id', $loanApplication->group_id))->orderBy('first_name')->get();

        return view('admin.loan-applications.show', ['application' => $loanApplication, 'eligibleWitnesses' => $eligible]);
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

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }

    private function formData(Request $request, LoanApplication $application, int $selectedMember): array
    {
        return [
            'application' => $application,
            'members' => Member::with('group')->where('status', 'active')->whereHas('activeGroupMembership')->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->orderBy('first_name')->get(),
            'products' => LoanProduct::where('status', true)->orderBy('name')->get(),
            'selectedMember' => $selectedMember,
        ];
    }
}
