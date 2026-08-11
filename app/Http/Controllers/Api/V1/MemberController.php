<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Resources\MemberResource;
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
            $member = Member::create([...$data, 'membership_number' => $numbers->member(), 'created_by' => $request->user()->id]);
            if ($kyc) {
                $member->kyc()->create($kyc);
            }
            $memberships->assign($member, MemberGroup::findOrFail($member->group_id), $member->admission_date ?? today());
            activity()->causedBy($request->user())->performedOn($member)->log('Member registered');

            return $member;
        });

        return response()->json(['success' => true, 'message' => 'Member created successfully.', 'data' => new MemberResource($member->load('branch', 'group', 'kyc'))], 201);
    }

    public function show(Member $member)
    {
        return response()->json(['success' => true, 'data' => new MemberResource($member->load('branch', 'group', 'kyc'))]);
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
}
