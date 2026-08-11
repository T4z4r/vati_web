<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;

class MemberPassbookController extends ApiController
{
    public function show(Member $member)
    {
        $loan = $member->loans()->withCount(['installments as installments_paid' => fn ($q) => $q->where('status', 'paid')])->whereIn('status', ['active', 'overdue', 'settled'])->latest('id')->first();

        return response()->json(['success' => true, 'data' => ['member' => ['membership_number' => $member->membership_number, 'name' => trim("{$member->first_name} {$member->middle_name} {$member->last_name}")], 'loan' => $loan ? ['loan_number' => $loan->loan_number, 'principal' => $loan->principal_amount, 'total_repayment' => $loan->total_repayment, 'paid' => round((float) $loan->total_repayment - (float) $loan->total_balance, 2), 'outstanding' => $loan->total_balance, 'installments_paid' => $loan->installments_paid, 'installments_total' => $loan->number_of_installments] : null, 'transactions' => $loan?->payments()->where('status', 'posted')->latest('paid_at')->get() ?? []]]);
    }
}
