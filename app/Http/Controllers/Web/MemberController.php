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
use Illuminate\Validation\Rule;

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

        return view('admin.members.create', $this->formData($request, new Member, $request->integer('group_id')));
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

    public function edit(Request $request, Member $member)
    {
        $member->load('kyc');

        return view('admin.members.create', $this->formData($request, $member, $member->group_id));
    }

    public function update(Request $request, Member $member, GroupMembershipService $memberships)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'group_id' => ['required', 'exists:member_groups,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('members', 'phone')->ignore($member)],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('members', 'national_id')->ignore($member)],
            'voter_id' => ['nullable', 'string', 'max:50', Rule::unique('members', 'voter_id')->ignore($member)],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'physical_address' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:100'],
            'admission_date' => ['nullable', 'date'],
            'passbook_issue_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'closed'])],
            'kyc' => ['nullable', 'array'],
            'kyc.household_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'kyc.household_monthly_expenses' => ['nullable', 'numeric', 'min:0'],
            'kyc.business_name' => ['nullable', 'string', 'max:200'],
            'kyc.business_type' => ['nullable', 'string', 'max:100'],
            'kyc.business_address' => ['nullable', 'string'],
            'kyc.mpesa_phone' => ['nullable', 'string', 'max:20'],
            'kyc.bank_account_number' => ['nullable', 'string', 'max:50'],
            'kyc.bank_account_name' => ['nullable', 'string', 'max:100'],
            'kyc.bank_name' => ['nullable', 'string', 'max:100'],
        ]);

        $group = MemberGroup::findOrFail($data['group_id']);
        if (! $group->status || (int) $group->branch_id !== (int) $data['branch_id']) {
            return back()->withInput()->with('error', 'The selected group must be active and belong to the selected branch.');
        }

        DB::transaction(function () use ($member, $data, $memberships, $group) {
            $kyc = Arr::pull($data, 'kyc');
            $groupChanged = (int) $member->group_id !== (int) $data['group_id'];
            $member->update($data);

            if ($kyc) {
                $member->kyc()->updateOrCreate(['member_id' => $member->id], $kyc);
            }

            if ($groupChanged) {
                $memberships->assign($member, $group, $member->admission_date ?? today());
            }
        });

        return redirect()->route('admin.members.show', $member)->with('success', 'Member updated successfully.');
    }

    public function destroy(Member $member)
    {
        if ($member->loans()->exists() || $member->loanApplications()->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->exists()) {
            return back()->with('error', 'This member has loan history and cannot be deleted.');
        }

        $member->delete();

        return redirect()->route('admin.members.index')->with('success', 'Member deleted.');
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

    private function formData(Request $request, Member $member, ?int $selectedGroup = null): array
    {
        $branchId = $this->branchId($request);

        return [
            'member' => $member,
            'branches' => Branch::where('status', true)->when($branchId, fn ($q, $id) => $q->whereKey($id))->get(),
            'groups' => MemberGroup::with('branch')->where('status', true)->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->orderBy('group_name')->get(),
            'selectedGroup' => $selectedGroup,
        ];
    }
}
