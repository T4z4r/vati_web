<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\GroupVisit;
use App\Models\MemberGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupVisitController extends ApiController
{
    public function index(Request $request)
    {
        $query = GroupVisit::with(['group', 'user'])
            ->when($request->group_id, fn ($q, $v) => $q->where('group_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->from, fn ($q, $v) => $q->where('visit_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('visit_date', '<=', $v))
            ->latest('visit_date');

        return response()->json(['success' => true, 'data' => $query->paginate($this->perPage($request))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:member_groups,id'],
            'visit_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $visit = GroupVisit::create([...$data, 'user_id' => $request->user()->id]);
        activity()->causedBy($request->user())->performedOn($visit)->withProperties(['group_id' => $data['group_id']])->log('Group visit recorded');

        return response()->json(['success' => true, 'message' => 'Group visit recorded.', 'data' => $visit->load('group', 'user')], 201);
    }

    public function show(GroupVisit $groupVisit)
    {
        return response()->json(['success' => true, 'data' => $visit = $groupVisit->load('group', 'user')]);
    }

    public function update(Request $request, GroupVisit $groupVisit)
    {
        $data = $request->validate([
            'visit_date' => ['sometimes', 'required', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $groupVisit->update($data);

        return response()->json(['success' => true, 'message' => 'Group visit updated.', 'data' => $groupVisit->refresh()->load('group', 'user')]);
    }

    public function destroy(GroupVisit $groupVisit)
    {
        $groupVisit->delete();

        return response()->noContent();
    }
}
