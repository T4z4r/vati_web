<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\CreditReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditReviewController extends ApiController
{
    public function assign(Request $request, LoanApplication $loanApplication, CreditReviewService $service)
    {
        $data = $request->validate(['credit_officer_id' => ['required', 'exists:users,id']]);

        return response()->json(['success' => true, 'data' => new LoanApplicationResource($service->assign($loanApplication, User::findOrFail($data['credit_officer_id']), $request->user()))]);
    }

    public function store(Request $request, LoanApplication $loanApplication, CreditReviewService $service)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['recommend', 'return', 'reject'])],
            'recommended_amount' => ['required_if:decision,recommend', 'nullable', 'numeric', 'gt:0'],
            'recommended_duration_months' => ['required_if:decision,recommend', 'nullable', 'integer', 'gt:0'],
            'overall_risk' => ['required', Rule::in(['low', 'medium', 'high'])],
            'remarks' => ['required_if:decision,return,reject', 'nullable', 'string'],
            'member_verified' => ['required', 'boolean'], 'group_membership_verified' => ['required', 'boolean'], 'documents_verified' => ['required', 'boolean'],
        ]);
        $review = $service->review($loanApplication, $request->user(), $data);

        return response()->json(['success' => true, 'data' => ['id' => $loanApplication->id, 'status' => $review->application->status->value, 'credit_review' => $review]]);
    }

    public function returnApplication(Request $request, LoanApplication $loanApplication, CreditReviewService $service)
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'min:5']]);

        return response()->json(['success' => true, 'data' => new LoanApplicationResource($service->returnByAdministrator($loanApplication, $request->user(), $data['remarks']))]);
    }
}
