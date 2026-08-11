<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
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

    public function storeRegion(Request $request)
    {
        Region::create($request->validate(['name' => ['required', 'max:150'], 'code' => ['nullable', 'max:30', 'unique:regions,code']]));

        return back()->with('success', 'Region created.');
    }

    public function storeArea(Request $request)
    {
        Area::create($request->validate(['region_id' => ['required', 'exists:regions,id'], 'name' => ['required', 'max:150'], 'code' => ['nullable', 'max:30', 'unique:areas,code']]));

        return back()->with('success', 'Area created.');
    }

    public function storeBranch(Request $request)
    {
        Branch::create($request->validate(['area_id' => ['required', 'exists:areas,id'], 'branch_code' => ['required', 'max:30', 'unique:branches,branch_code'], 'branch_name' => ['required', 'max:150'], 'phone' => ['nullable', 'max:20'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string']]));

        return back()->with('success', 'Branch created.');
    }
}
