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
        return response()->json(['success' => true, 'data' => Region::create([...$this->data($request), 'code' => $numbers->region()])], 201);
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
        return $request->validate(['name' => ['required', 'string', 'max:150'], 'status' => ['sometimes', 'boolean']]);
    }
}
