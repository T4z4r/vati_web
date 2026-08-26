<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Region;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;

class RegionController extends ApiController
{
    public function index(Request $request)
    {
        return Region::with('areas')->latest()->paginate($this->perPage($request));
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $region = Region::create([...$this->data($request), 'code' => $numbers->region()]);
        activity()->causedBy($request->user())->performedOn($region)->log('Region created');

        return response()->json(['success' => true, 'data' => $region], 201);
    }

    public function show(Region $region)
    {
        return response()->json(['success' => true, 'data' => $region->load('areas.branches')]);
    }

    public function update(Request $request, Region $region)
    {
        $region->update($this->data($request, $region));
        activity()->causedBy($request->user())->performedOn($region)->withProperties(['changed_fields' => array_keys($region->getChanges())])->log('Region updated');

        return response()->json(['success' => true, 'data' => $region->refresh()]);
    }

    public function destroy(Request $request, Region $region)
    {
        activity()->causedBy($request->user())->withProperties(['deleted_region' => ['id' => $region->id, 'name' => $region->name]])->log('Region deleted');
        $region->delete();

        return response()->noContent();
    }

    private function data(Request $request, ?Region $region = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:150'], 'status' => ['sometimes', 'boolean']]);
    }
}
