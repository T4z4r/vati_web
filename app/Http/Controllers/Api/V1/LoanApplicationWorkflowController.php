<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Services\LoanApprovalService;
use Illuminate\Http\Request;

class LoanApplicationWorkflowController extends ApiController
{
    public function submit(Request $request, LoanApplication $loanApplication)
    {
        abort_unless($loanApplication->status === ApplicationStatus::DRAFT, 409, 'Only draft applications can be submitted.');
        $loanApplication->update(['status' => ApplicationStatus::SUBMITTED, 'submitted_at' => now()]);
        activity()->causedBy($request->user())->performedOn($loanApplication)->log('Loan application submitted');

        return response()->json(['success' => true, 'data' => new LoanApplicationResource($loanApplication->refresh())]);
    }

    public function approve(Request $request, LoanApplication $loanApplication, LoanApprovalService $service)
    {
        $data = $request->validate(['remarks' => ['nullable', 'string']]);

        return response()->json(['success' => true, 'data' => new LoanApplicationResource($service->decide($loanApplication, $request->user(), 'approved', $data['remarks'] ?? null))]);
    }

    public function reject(Request $request, LoanApplication $loanApplication, LoanApprovalService $service)
    {
        $data = $request->validate(['remarks' => ['required', 'string']]);

        return response()->json(['success' => true, 'data' => new LoanApplicationResource($service->decide($loanApplication, $request->user(), 'rejected', $data['remarks']))]);
    }
}
