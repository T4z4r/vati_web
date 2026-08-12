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
        return view('admin.groups.create', $this->formData($request, new MemberGroup));
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $data = $this->data($request);
        $group = MemberGroup::create([...$data, 'group_code' => $numbers->group()]);

        return redirect()->route('admin.groups.show', $group)->with('success', 'Group created successfully.');
    }

    public function show(MemberGroup $group)
    {
        $group->load(['branch', 'loanOfficer'])->loadCount(['members', 'loans', 'loanApplications']);

        return view('admin.groups.show', ['group' => $group, 'members' => $group->members()->latest()->limit(20)->get(), 'loans' => $group->loans()->with('member')->latest()->limit(10)->get(), 'applications' => $group->loanApplications()->with('member')->latest()->limit(10)->get()]);
    }

    public function edit(Request $request, MemberGroup $group)
    {
        return view('admin.groups.create', $this->formData($request, $group));
    }

    public function update(Request $request, MemberGroup $group)
    {
        $group->update($this->data($request, true));

        return redirect()->route('admin.groups.show', $group)->with('success', 'Group updated successfully.');
    }

    public function destroy(MemberGroup $group)
    {
        if ($group->members()->exists() || $group->loanApplications()->exists() || $group->loans()->exists()) {
            return back()->with('error', 'This group has members or lending history and cannot be deleted.');
        }

        $group->delete();

        return redirect()->route('admin.groups.index')->with('success', 'Group deleted.');
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }

    private function data(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'group_name' => ['required', 'max:150'],
            'meeting_day' => ['nullable', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'meeting_time' => ['nullable'],
            'location' => ['nullable', 'max:255'],
            'ward' => ['nullable', 'max:100'],
            'district' => ['nullable', 'max:100'],
            'region' => ['nullable', 'max:100'],
            'loan_officer_id' => ['nullable', 'exists:users,id'],
            'status' => [$editing ? 'required' : 'nullable', 'boolean'],
        ]);
    }

    private function formData(Request $request, MemberGroup $group): array
    {
        return [
            'group' => $group,
            'branches' => Branch::where('status', true)->when($this->branchId($request), fn ($q, $id) => $q->whereKey($id))->get(),
            'officers' => User::role('loan_officer')->where('status', true)->orderBy('name')->get(),
        ];
    }
}
