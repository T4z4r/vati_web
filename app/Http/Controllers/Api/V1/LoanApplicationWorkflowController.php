<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Services\ApplicationComplianceService;
use App\Services\LoanApprovalService;
use Illuminate\Http\Request;

class LoanApplicationWorkflowController extends ApiController
{
    public function submit(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $compliance, LoanApprovalService $service)
    {
        abort_unless(in_array($loanApplication->status, [ApplicationStatus::DRAFT, ApplicationStatus::RETURNED, ApplicationStatus::REVERTED], true), 409, 'Only draft, returned, or reverted applications can be submitted.');
        $compliance->assertReadyForSubmission($loanApplication);
        $loanApplication->update([
            'status' => ApplicationStatus::SUBMITTED,
            'submitted_at' => now(),
            'credit_review_attempt' => in_array($loanApplication->status, [ApplicationStatus::RETURNED, ApplicationStatus::REVERTED], true) ? $loanApplication->credit_review_attempt + 1 : $loanApplication->credit_review_attempt,
        ]);
        activity()->causedBy($request->user())->performedOn($loanApplication)->log('Loan application submitted');
        $loanApplication = $service->autoApproveIfEnabled($loanApplication, $request->user()) ?? $loanApplication->refresh();

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
