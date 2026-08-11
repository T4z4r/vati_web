<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Services\SecurityAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityAccountController extends ApiController
{
    public function show(Member $member)
    {
        return response()->json(['success' => true, 'data' => $member->securityAccount?->load('transactions')]);
    }

    public function store(Request $request, Member $member, SecurityAccountService $service)
    {
        $data = $request->validate(['transaction_type' => ['required', Rule::in(['deposit', 'withdrawal', 'loan_offset', 'refund', 'adjustment'])], 'amount' => ['required', 'numeric', 'gt:0'], 'loan_id' => ['nullable', 'exists:loans,id'], 'remarks' => ['nullable', 'string'], 'transaction_date' => ['nullable', 'date']]);

        return response()->json(['success' => true, 'data' => $service->transact($member, $request->user(), $data['transaction_type'], (float) $data['amount'], $data)], 201);
    }
}
