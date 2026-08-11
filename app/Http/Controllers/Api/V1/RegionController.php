<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegionController extends ApiController
{
    public function index(Request $request)
    {
        return Region::with('areas')->paginate($this->perPage($request));
    }

    public function store(Request $request)
    {
        return response()->json(['success' => true, 'data' => Region::create($this->data($request))], 201);
    }

    public function show(Region $region)
    {
        return response()->json(['success' => true, 'data' => $region->load('areas.branches')]);
    }

    public function update(Request $request, Region $region)
    {
        $region->update($this->data($request, $region));

        return response()->json(['success' => true, 'data' => $region->refresh()]);
    }

    public function destroy(Region $region)
    {
        $region->delete();

        return response()->noContent();
    }

    private function data(Request $request, ?Region $region = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'string', 'max:30', Rule::unique('regions')->ignore($region)], 'status' => ['sometimes', 'boolean']]);
    }
}
