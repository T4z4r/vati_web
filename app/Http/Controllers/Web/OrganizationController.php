<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        return view('admin.organization.index', [
            'regions' => Region::withCount('areas')->orderBy('name')->get(),
            'areas' => Area::with('region')->withCount('branches')->orderBy('name')->get(),
            'branches' => Branch::with('area.region')->withCount(['members', 'groups'])->orderBy('branch_name')->get(),
        ]);
    }

    public function storeRegion(Request $request, NumberGeneratorService $numbers)
    {
        Region::create([...$request->validate(['name' => ['required', 'max:150']]), 'code' => $numbers->region()]);

        return back()->with('success', 'Region created.');
    }

    public function storeArea(Request $request, NumberGeneratorService $numbers)
    {
        Area::create([...$request->validate(['region_id' => ['required', 'exists:regions,id'], 'name' => ['required', 'max:150']]), 'code' => $numbers->area()]);

        return back()->with('success', 'Area created.');
    }

    public function storeBranch(Request $request, NumberGeneratorService $numbers)
    {
        Branch::create([...$request->validate(['area_id' => ['required', 'exists:areas,id'], 'branch_name' => ['required', 'max:150'], 'phone' => ['nullable', 'max:20'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string']]), 'branch_code' => $numbers->branch()]);

        return back()->with('success', 'Branch created.');
    }
}
