<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\AssetType;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Services\GroupMembershipService;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends ApiController
{
    public function index(Request $request)
    {
        $query = $this->branchScope(Member::with('branch', 'group'), $request)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('membership_number', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))->when($request->group_id, fn ($q, $v) => $q->where('group_id', $v))->when($request->status, fn ($q, $v) => $q->where('status', $v));

        return MemberResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreMemberRequest $request, NumberGeneratorService $numbers, GroupMembershipService $memberships)
    {
        $member = DB::transaction(function () use ($request, $numbers, $memberships) {
            $data = $request->validated();
            $kyc = Arr::pull($data, 'kyc');
            $nominees = Arr::pull($data, 'nominees', []);
            $familyMembers = Arr::pull($data, 'family_members', []);
            $assets = Arr::pull($data, 'assets', []);
            $member = Member::create([...$data, 'membership_number' => $numbers->member(), 'created_by' => $request->user()->id]);
            if ($kyc) {
                $member->kyc()->create($kyc);
            }
            $memberships->assign($member, MemberGroup::findOrFail($member->group_id), $member->admission_date ?? today());
            foreach ($nominees as $nominee) {
                $member->nominees()->create([...$nominee, 'attested_at' => now()]);
            }
            foreach ($familyMembers as $familyMember) {
                $member->familyMembers()->create($familyMember);
            }
            $this->createAssets($member, $assets);
            activity()->causedBy($request->user())->performedOn($member)->log('Member registered');

            return $member;
        });

        return response()->json(['success' => true, 'message' => 'Member created successfully.', 'data' => new MemberResource($this->loadDetail($member))], 201);
    }

    public function show(Member $member)
    {
        return response()->json(['success' => true, 'data' => new MemberResource($this->loadDetail($member))]);
    }

    public function update(Request $request, Member $member, GroupMembershipService $memberships)
    {
        $data = $request->validate(['first_name' => ['sometimes', 'required', 'max:100'], 'middle_name' => ['nullable', 'max:100'], 'last_name' => ['sometimes', 'required', 'max:100'], 'phone' => ['sometimes', 'required', 'max:20', Rule::unique('members')->ignore($member)], 'group_id' => ['sometimes', 'required', 'exists:member_groups,id'], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended', 'closed'])]]);
        $groupId = Arr::pull($data, 'group_id');
        $member->update($data);
        if ($groupId) {
            $group = MemberGroup::findOrFail($groupId);
            $user = $request->user();
            abort_if(! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $group->branch_id !== $user->branch_id, 403, 'You cannot transfer a member to another branch.');
            $memberships->assign($member, $group);
        }

        return response()->json(['success' => true, 'data' => new MemberResource($member->refresh())]);
    }

    public function destroy(Member $member)
    {
        $member->delete();

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

    private function createAssets(Member $member, array $assets): void
    {
        foreach ($assets as $asset) {
            $name = Arr::pull($asset, 'name');
            $category = Arr::pull($asset, 'category');
            $assetType = AssetType::firstOrCreate(['name' => $name], ['category' => $category, 'status' => true]);
            $member->assets()->create([...$asset, 'asset_type_id' => $assetType->id]);
        }
    }
}
