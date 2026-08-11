<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\OnboardLoanApplicationRequest;
use App\Http\Requests\OnboardMemberRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Http\Resources\MemberResource;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends ApiController
{
    public function group(Request $request, OnboardingService $service)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'group_code' => ['required', 'string', 'max:30', 'unique:member_groups,group_code'],
            'group_name' => ['required', 'string', 'max:150'],
            'meeting_day' => ['required', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'region' => ['nullable', 'string', 'max:100'], 'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'], 'location' => ['required', 'string', 'max:255'],
            'loan_officer_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('branch_id', $request->integer('branch_id'))->where('status', true))],
        ]);
        $group = $service->group([...$data, 'status' => true], $request->user());

        return response()->json([
            'success' => true, 'message' => 'Group onboarding completed.', 'data' => $group,
            'next_steps' => ['Register members using POST /api/v1/onboarding/members.', 'Schedule the first group meeting.'],
        ], 201);
    }

    public function member(OnboardMemberRequest $request, OnboardingService $service)
    {
        $member = $service->member($request->validated(), $request->user());

        return response()->json([
            'success' => true, 'message' => 'Member onboarding completed.', 'data' => new MemberResource($member),
            'next_steps' => ['Complete any remaining KYC evidence.', 'Create a draft loan application when the member is eligible.'],
        ], 201);
    }

    public function loanApplication(OnboardLoanApplicationRequest $request, OnboardingService $service)
    {
        $application = $service->loanApplication($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Loan application onboarding completed.',
            'data' => new LoanApplicationResource($application),
            'next_steps' => [
                'Capture applicant consent, signature, and thumbprint.',
                'Capture two guarantor declarations and required documents.',
                'Verify checklist documents, add group witnesses, then submit the application.',
            ],
        ], 201);
    }
}
