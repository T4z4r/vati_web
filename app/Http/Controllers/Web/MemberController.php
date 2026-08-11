<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Services\GroupMembershipService;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = Member::with(['branch', 'group'])->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->when($request->group_id, fn ($q, $id) => $q->where('group_id', $id))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('membership_number', 'like', "%{$v}%")->orWhere('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%")->orWhere('phone', 'like', "%{$v}%")))->latest()->paginate(20)->withQueryString();

        return view('admin.members.index', ['members' => $members, 'groups' => MemberGroup::where('status', true)->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->orderBy('group_name')->get()]);
    }

    public function create(Request $request)
    {
        $branchId = $this->branchId($request);

        return view('admin.members.create', ['branches' => Branch::where('status', true)->when($branchId, fn ($q, $id) => $q->whereKey($id))->get(), 'groups' => MemberGroup::with('branch')->where('status', true)->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->orderBy('group_name')->get()]);
    }

    public function store(StoreMemberRequest $request, NumberGeneratorService $numbers, GroupMembershipService $memberships)
    {
        $member = DB::transaction(function () use ($request, $numbers, $memberships) {
            $data = $request->validated();
            $kyc = Arr::pull($data, 'kyc');
            $member = Member::create([...$data, 'membership_number' => $numbers->member(), 'created_by' => $request->user()->id]);
            if ($kyc) {
                $member->kyc()->create($kyc);
            }$memberships->assign($member, MemberGroup::findOrFail($member->group_id), $member->admission_date ?? today());
            activity()->causedBy($request->user())->performedOn($member)->log('Member registered');

            return $member;
        });

        return redirect()->route('admin.members.show', $member)->with('success', 'Member registered successfully.');
    }

    public function show(Member $member)
    {
        return view('admin.members.show', ['member' => $member->load(['branch', 'group', 'kyc', 'activeGroupMembership', 'securityAccount.transactions', 'passbookReplacements']), 'applications' => $member->loanApplications()->with('product')->latest()->get(), 'loans' => $member->loans()->with('product')->latest()->get()]);
    }

    public function updateKyc(Request $request, Member $member)
    {
        $data = $request->validate(['mpesa_phone' => ['nullable', 'max:20'], 'bank_account_number' => ['nullable', 'max:100'], 'bank_account_name' => ['nullable', 'max:150'], 'bank_name' => ['nullable', 'max:150'], 'house_number' => ['nullable', 'max:100'], 'police_station' => ['nullable', 'max:150'], 'business_name' => ['nullable', 'max:150'], 'business_type' => ['nullable', 'max:150'], 'business_address' => ['nullable', 'string'], 'household_monthly_income' => ['nullable', 'numeric', 'min:0'], 'household_monthly_expenses' => ['nullable', 'numeric', 'min:0'], 'number_of_dependants' => ['nullable', 'integer', 'min:0'], 'head_of_household' => ['nullable', 'max:150'], 'house_ownership_status' => ['nullable', 'max:100']]);
        $member->kyc()->updateOrCreate(['member_id' => $member->id], $data);

        return back()->with('success', 'Member KYC updated.');
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }
}
