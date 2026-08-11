<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MemberGroup;
use App\Models\User;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = MemberGroup::with(['branch', 'loanOfficer'])->withCount(['members', 'loans'])
            ->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->search, fn ($q, $value) => $q->where(fn ($q) => $q->where('group_name', 'like', "%{$value}%")->orWhere('group_code', 'like', "%{$value}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.groups.index', compact('groups'));
    }

    public function create(Request $request)
    {
        return view('admin.groups.create', ['branches' => Branch::where('status', true)->when($this->branchId($request), fn ($q, $id) => $q->whereKey($id))->get(), 'officers' => User::role('loan_officer')->where('status', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $data = $request->validate(['branch_id' => ['required', 'exists:branches,id'], 'group_name' => ['required', 'max:150'], 'meeting_day' => ['nullable', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])], 'meeting_time' => ['nullable'], 'location' => ['nullable', 'max:255'], 'ward' => ['nullable', 'max:100'], 'district' => ['nullable', 'max:100'], 'region' => ['nullable', 'max:100'], 'loan_officer_id' => ['nullable', 'exists:users,id']]);
        $group = MemberGroup::create([...$data, 'group_code' => $numbers->group()]);

        return redirect()->route('admin.groups.show', $group)->with('success', 'Group created successfully.');
    }

    public function show(MemberGroup $group)
    {
        $group->load(['branch', 'loanOfficer'])->loadCount(['members', 'loans', 'loanApplications']);

        return view('admin.groups.show', ['group' => $group, 'members' => $group->members()->latest()->limit(20)->get(), 'loans' => $group->loans()->with('member')->latest()->limit(10)->get(), 'applications' => $group->loanApplications()->with('member')->latest()->limit(10)->get()]);
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }
}
