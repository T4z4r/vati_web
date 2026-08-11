<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_number' => $this->loan_number,
            'member' => new MemberResource($this->whenLoaded('member')),
            'product' => $this->whenLoaded('product'),
            'group' => $this->whenLoaded('group'),
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'total_repayment' => $this->total_repayment,
            'principal_balance' => $this->principal_balance,
            'interest_balance' => $this->interest_balance,
            'total_balance' => $this->total_balance,
            'number_of_installments' => $this->number_of_installments,
            'installment_amount' => $this->installment_amount,
            'status' => $this->status->value,
            'disbursement_date' => $this->disbursement_date,
            'maturity_date' => $this->maturity_date,
            'installments' => $this->whenLoaded('installments'),
            'payments' => $this->whenLoaded('payments'),
        ];
    }
}
