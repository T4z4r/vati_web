<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\SecurityAccountService;
use DomainException;
use Illuminate\Http\Request;

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
}
