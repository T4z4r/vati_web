<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberSecurityPayoffService;
use App\Services\SecurityAccountService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityController extends Controller
{
    public function store(Request $request, Member $member, SecurityAccountService $service)
    {
        $data = $request->validate(['transaction_type' => ['required', 'in:deposit,withdrawal,refund,adjustment,loan_offset'], 'amount' => ['required', 'numeric', 'gt:0'], 'remarks' => ['nullable', 'string']]);
        try {
            $service->transact($member, $request->user(), $data['transaction_type'], (float) $data['amount'], $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Security transaction posted.');
    }

    public function payOff(Request $request, Member $member, MemberSecurityPayoffService $service)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'payout_method' => ['nullable', Rule::in(['cash', 'mpesa', 'airtel_money', 'mixx', 'halopesa', 'bank_transfer'])],
            'payout_reference' => ['nullable', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ]);

        try {
            $result = $service->payOff($member, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result['message']);
    }
}
