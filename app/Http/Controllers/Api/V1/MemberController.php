<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class MemberController extends ApiController
{
    public function index(Request $request)
    {
        $query = $this->branchScope(Member::with('branch', 'group'), $request)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('membership_number', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))->when($request->group_id, fn ($q, $v) => $q->where('group_id', $v))->when($request->status, fn ($q, $v) => $q->where('status', $v));

        return MemberResource::collection($query->latest()->paginate($this->perPage($request)));
    }

    public function store(StoreMemberRequest $request, OnboardingService $service)
    {
        $member = $service->member($request->validated(), $request->user());

        return response()->json(['success' => true, 'message' => 'Member created successfully.', 'data' => new MemberResource($this->loadDetail($member))], 201);
    }

    public function show(Member $member)
    {
        return response()->json(['success' => true, 'data' => new MemberResource($this->loadDetail($member))]);
    }

    public function update(StoreMemberRequest $request, Member $member, OnboardingService $service)
    {
        $member = $service->updateMember($member, $request->validated(), $request->user());

        return response()->json(['success' => true, 'message' => 'Member updated successfully.', 'data' => new MemberResource($this->loadDetail($member))]);
    }

    public function destroy(Request $request, Member $member)
    {
        abort_if($member->loans()->exists() || $member->loanApplications()->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->exists(), 409, 'This member has loan history and cannot be deleted.');
        $force = $request->boolean('force');
        $force ? $member->forceDelete() : $member->delete();
        activity()->causedBy($request->user())->performedOn($member)->withProperties(['forced' => $force])->log($force ? 'Member permanently deleted' : 'Member deleted');

        return response()->noContent();
    }

    private function loadDetail(Member $member): Member
    {
        return $member->load([
            'branch.manager',
            'group.loanOfficer',
            'createdBy',
            'kyc',
            'activeGroupMembership',
            'nominees',
            'familyMembers',
            'assets.assetType',
            'documents.uploadedBy',
            'securityAccount.transactions',
            'passbookReplacements',
            'loanApplications.product',
            'loanApplications.loan',
            'loans.product',
            'loans.application.guarantors',
            'loans.cycles',
            'loans.installments',
            'loans.installmentRecords.collector',
            'loans.payments',
            'loans.securityTransactions.collectedBy',
            'loans.securityTransactions.approvedBy',
            'loans.disbursement',
            'loans.settlement',
            'loans.clearance',
            'loans.defaultNotices',
        ]);
    }
}
