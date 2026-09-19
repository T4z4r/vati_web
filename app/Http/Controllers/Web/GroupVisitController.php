<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GroupVisit;
use App\Models\MemberGroup;
use App\Services\ExportService;
use Illuminate\Http\Request;

class GroupVisitController extends Controller
{
    public function index(Request $request)
    {
        $visits = $this->filteredQuery($request)->latest('visit_date')->paginate(20)->withQueryString();

        $groups = MemberGroup::where('status', true)->orderBy('group_name')->get();

        return view('admin.group-visits.index', compact('visits', 'groups'));
    }

    private function filteredQuery(Request $request)
    {
        return GroupVisit::with(['group', 'user'])
            ->when($request->group_id, fn ($q, $v) => $q->where('group_id', $v))
            ->when($request->from, fn ($q, $v) => $q->where('visit_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('visit_date', '<=', $v));
    }

    public function export(Request $request, ExportService $exporter, string $format)
    {
        $rows = $this->filteredQuery($request)->latest('visit_date')->get()->map(fn (GroupVisit $visit) => [
            'visit_date' => $visit->visit_date->format('d M Y'),
            'group' => $visit->group?->group_name,
            'officer' => $visit->user?->name,
            'purpose' => $visit->purpose ?: '-',
            'location' => $visit->location ?: '-',
        ])->values()->all();

        return $exporter->export(
            'VATI Group Visits List',
            ['Visit Date', 'Group', 'Officer', 'Purpose', 'Location'],
            $rows,
            'VATI-group-visits-'.now()->format('Ymd-His'),
            $format
        );
    }

    public function create(Request $request)
    {
        $groups = MemberGroup::where('status', true)->orderBy('group_name')->get();
        $group = MemberGroup::find($request->group_id);

        return view('admin.group-visits.form', compact('groups', 'group'));
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
        activity()->causedBy($request->user())->performedOn($visit)->log('Group visit recorded');

        return redirect()->route('admin.group-visits.index')->with('success', 'Group visit recorded.');
    }

    public function show(GroupVisit $groupVisit)
    {
        $groupVisit->load('group', 'user');

        return view('admin.group-visits.show', ['visit' => $groupVisit]);
    }

    public function destroy(GroupVisit $groupVisit)
    {
        $groupVisit->delete();

        return redirect()->route('admin.group-visits.index')->with('success', 'Group visit deleted.');
    }
}
