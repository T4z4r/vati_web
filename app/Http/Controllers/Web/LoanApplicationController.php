<?php

namespace App\Http\Controllers\Web;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\ApplicationComplianceService;
use App\Services\LoanApprovalService;
use App\Services\NumberGeneratorService;
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
        return view('admin.loan-applications.create', ['members' => Member::with('group')->where('status', 'active')->whereHas('activeGroupMembership')->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->orderBy('first_name')->get(), 'products' => LoanProduct::where('status', true)->orderBy('name')->get(), 'selectedMember' => $request->integer('member_id')]);
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $data = $request->validate(['member_id' => ['required', 'exists:members,id'], 'loan_product_id' => ['required', 'exists:loan_products,id'], 'application_type' => ['required', 'in:main,refinance,top_up'], 'requested_amount' => ['required', 'numeric', 'gt:0'], 'duration_months' => ['required', 'integer', 'gt:0'], 'loan_purpose' => ['nullable', 'string'], 'business_summary' => ['nullable', 'string'], 'core_business_income' => ['nullable', 'numeric', 'min:0'], 'other_income' => ['nullable', 'numeric', 'min:0'], 'business_expenses' => ['nullable', 'numeric', 'min:0'], 'household_expenses' => ['nullable', 'numeric', 'min:0']]);
        try {
            $application = DB::transaction(function () use ($data, $request, $numbers) {
                $member = Member::with(['group', 'activeGroupMembership'])->lockForUpdate()->findOrFail($data['member_id']);
                $user = $request->user();
                if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $member->branch_id !== $user->branch_id) {
                    abort(403);
                }
                $product = LoanProduct::findOrFail($data['loan_product_id']);
                if ($member->status !== 'active' || ! $member->group?->status || $member->activeGroupMembership?->group_id !== $member->group_id) {
                    throw new DomainException('Member must have a valid active group membership.');
                }
                if ($data['requested_amount'] < $product->minimum_amount || $data['requested_amount'] > $product->maximum_amount) {
                    throw new DomainException('Requested amount is outside the product limits.');
                }
                if ($data['duration_months'] < $product->minimum_duration_months || $data['duration_months'] > $product->maximum_duration_months) {
                    throw new DomainException('Duration is outside the product limits.');
                }
                $application = LoanApplication::create(['application_number' => $numbers->application(), 'member_id' => $member->id, 'loan_product_id' => $product->id, 'group_id' => $member->group_id, 'branch_id' => $member->branch_id, 'application_type' => $data['application_type'], 'requested_amount' => $data['requested_amount'], 'duration_months' => $data['duration_months'], 'loan_purpose' => $data['loan_purpose'] ?? null, 'business_summary' => $data['business_summary'] ?? null, 'status' => 'draft', 'created_by' => $request->user()->id]);
                $income = ($data['core_business_income'] ?? 0) + ($data['other_income'] ?? 0);
                $expenses = ($data['business_expenses'] ?? 0) + ($data['household_expenses'] ?? 0);
                $application->assessment()->create(['core_business_income' => $data['core_business_income'] ?? 0, 'other_income' => $data['other_income'] ?? 0, 'business_expenses' => $data['business_expenses'] ?? 0, 'household_expenses' => $data['household_expenses'] ?? 0, 'monthly_profit' => $income - ($data['business_expenses'] ?? 0), 'disposable_income' => $income - $expenses]);

                return $application;
            });
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.loan-applications.show', $application)->with('success', 'Loan application created.');
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
}
