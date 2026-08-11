<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanAdministrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanAdministrationController extends ApiController
{
    public function replacePassbook(Request $request, Member $member, LoanAdministrationService $service)
    {
        $data = $request->validate(['reason' => ['required', Rule::in(['lost', 'damaged'])], 'payment_reference' => ['required', 'max:100'], 'remarks' => ['nullable', 'string']]);

        return response()->json(['success' => true, 'data' => $service->replacePassbook($member, $request->user(), $data)], 201);
    }

    public function defaultNotice(Request $request, Loan $loan, LoanAdministrationService $service)
    {
        $data = $request->validate(['delivery_method' => ['required', Rule::in(['hand', 'sms', 'email', 'registered_mail'])], 'delivery_reference' => ['nullable', 'max:150'], 'notice_text' => ['nullable', 'string']]);

        return response()->json(['success' => true, 'data' => $service->issueDefaultNotice($loan, $request->user(), $data)], 201);
    }

    public function clearance(Request $request, Loan $loan, LoanAdministrationService $service)
    {
        $data = $request->validate(['comments' => ['nullable', 'string'], 'manager_signature' => ['required', 'image', 'max:5120']]);

        return response()->json(['success' => true, 'data' => $service->authorizeClearance($loan, $request->user(), $data, $request->file('manager_signature'))], 201);
    }
}
