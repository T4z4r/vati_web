<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\MemberGroup;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends ApiController
{
    public function index(Request $request)
    {
        return $this->branchScope(MemberGroup::with('branch', 'loanOfficer'), $request)->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('group_name', 'like', "%{$s}%")->orWhere('group_code', 'like', "%{$s}%")))->paginate($this->perPage($request));
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $group = MemberGroup::create([...$this->validated($request), 'group_code' => $numbers->group()]);

        return response()->json(['success' => true, 'message' => 'Group created successfully.', 'data' => $group], 201);
    }

    public function show(MemberGroup $group)
    {
        return response()->json(['success' => true, 'data' => $group->load('branch', 'loanOfficer')]);
    }

    public function update(Request $request, MemberGroup $group)
    {
        $group->update($this->validated($request, $group));

        return response()->json(['success' => true, 'data' => $group->refresh()]);
    }

    public function destroy(MemberGroup $group)
    {
        $group->delete();

        return response()->noContent();
    }

    public function members(Request $request, MemberGroup $group)
    {
        return $group->members()->paginate($this->perPage($request));
    }

    private function validated(Request $request, ?MemberGroup $group = null): array
    {
        return $request->validate(['branch_id' => ['required', 'exists:branches,id'], 'group_name' => ['required', 'max:150'], 'meeting_day' => ['nullable', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])], 'meeting_time' => ['nullable', 'date_format:H:i'], 'region' => ['nullable', 'max:100'], 'district' => ['nullable', 'max:100'], 'ward' => ['nullable', 'max:100'], 'location' => ['nullable', 'max:255'], 'loan_officer_id' => ['nullable', 'exists:users,id'], 'status' => ['sometimes', 'boolean']]);
    }
}
