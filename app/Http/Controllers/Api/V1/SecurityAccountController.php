<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Services\MemberSecurityPayoffService;
use App\Services\SecurityAccountService;
use DomainException;
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

    public function payOff(Request $request, Member $member, MemberSecurityPayoffService $service)
    {
        abort_unless($request->user()->can('manage-security'), 403, 'You are not allowed to pay off member security.');

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'payout_method' => ['nullable', Rule::in(['cash', 'mpesa', 'airtel_money', 'mixx', 'halopesa', 'bank_transfer'])],
            'payout_reference' => ['nullable', 'max:100'],
            'payoff_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        try {
            $result = $service->payOff($member, $request->user(), $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result]);
    }
}
