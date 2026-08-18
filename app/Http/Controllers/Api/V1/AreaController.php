<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Area;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;

class AreaController extends ApiController
{
    public function index(Request $request)
    {
        return Area::with('region')->when($request->region_id, fn ($q, $v) => $q->where('region_id', $v))->latest()->paginate($this->perPage($request));
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        return response()->json(['success' => true, 'data' => Area::create([...$this->data($request), 'code' => $numbers->area()])], 201);
    }

    public function show(Area $area)
    {
        return response()->json(['success' => true, 'data' => $area->load('region', 'branches')]);
    }

    public function update(Request $request, Area $area)
    {
        $area->update($this->data($request, $area));

        return response()->json(['success' => true, 'data' => $area->refresh()]);
    }

    public function destroy(Area $area)
    {
        $area->delete();

        return response()->noContent();
    }

    private function data(Request $request, ?Area $area = null): array
    {
        return $request->validate(['region_id' => ['required', 'exists:regions,id'], 'name' => ['required', 'string', 'max:150'], 'status' => ['sometimes', 'boolean']]);
    }
}
