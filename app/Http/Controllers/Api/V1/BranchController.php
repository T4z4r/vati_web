<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;

class BranchController extends ApiController
{
    public function index(Request $request)
    {
        return Branch::with('area.region')->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('branch_name', 'like', "%{$s}%")->orWhere('branch_code', 'like', "%{$s}%")))->latest()->paginate($this->perPage($request));
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $branch = Branch::create([...$this->validated($request), 'branch_code' => $numbers->branch()]);

        return response()->json(['success' => true, 'message' => 'Branch created successfully.', 'data' => $branch], 201);
    }

    public function show(Branch $branch)
    {
        return response()->json(['success' => true, 'data' => $branch->load('area.region', 'manager')]);
    }

    public function update(Request $request, Branch $branch)
    {
        $branch->update($this->validated($request, $branch));

        return response()->json(['success' => true, 'data' => $branch->refresh()]);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate(['area_id' => ['nullable', 'exists:areas,id'], 'branch_name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:20'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string'], 'manager_id' => ['nullable', 'exists:users,id'], 'status' => ['sometimes', 'boolean']]);
    }
}
