<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\OnboardLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Services\ApplicationDetailService;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class LoanApplicationController extends ApiController
{
    public function index(Request $request)
    {
        $query = $this->branchScope(LoanApplication::with('member', 'product'), $request)->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->member_id, fn ($q, $v) => $q->where('member_id', $v));

        return LoanApplicationResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(OnboardLoanApplicationRequest $request, OnboardingService $service)
    {
        $application = $service->loanApplication($request->validated(), $request->user());

        return response()->json(['success' => true, 'message' => 'Loan application created.', 'data' => new LoanApplicationResource($application)], 201);
    }

    public function show(LoanApplication $loanApplication, ApplicationDetailService $detail)
    {
        return response()->json(['success' => true, 'data' => $detail->build($loanApplication)]);
    }

    public function update(OnboardLoanApplicationRequest $request, LoanApplication $loanApplication, OnboardingService $service)
    {
        $application = $service->updateLoanApplication($loanApplication, $request->validated(), $request->user());

        return response()->json(['success' => true, 'message' => 'Loan application updated.', 'data' => new LoanApplicationResource($application)]);
    }

    public function destroy(LoanApplication $loanApplication)
    {
        abort_unless($loanApplication->status->value === 'draft', 409, 'Only draft applications can be deleted.');
        $loanApplication->delete();

        return response()->noContent();
    }
}
