<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;

class LoanController extends ApiController
{
    public function index(Request $request)
    {
        $query = $this->branchScope(Loan::with('member', 'product'), $request)->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->member_id, fn ($q, $v) => $q->where('member_id', $v));

        return LoanResource::collection($query->paginate($this->perPage($request)));
    }

    public function show(Loan $loan)
    {
        return response()->json(['success' => true, 'data' => new LoanResource($loan->load('member', 'product', 'application', 'installments', 'payments.allocations'))]);
    }

    public function schedule(Loan $loan)
    {
        return response()->json(['success' => true, 'data' => $loan->installments()->orderBy('installment_number')->get()]);
    }
}
