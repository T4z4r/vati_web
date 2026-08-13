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
            'application' => $this->whenLoaded('application'),
            'loan_cycle' => $this->loan_cycle,
            'business_name' => $this->business_name,
            'interest_rate' => $this->interest_rate,
            'principal_amount' => $this->principal_amount,
            'adjusted_principal_amount' => $this->adjusted_principal_amount,
            'interest_amount' => $this->interest_amount,
            'total_repayment' => $this->total_repayment,
            'principal_balance' => $this->principal_balance,
            'interest_balance' => $this->interest_balance,
            'total_balance' => $this->total_balance,
            'number_of_installments' => $this->number_of_installments,
            'installment_amount' => $this->installment_amount,
            'weekly_installment' => $this->weekly_installment,
            'admission_fee' => $this->admission_fee,
            'processing_fee' => $this->processing_fee,
            'transaction_charges' => $this->transaction_charges,
            'other_charges' => $this->other_charges,
            'total_fees_and_vat' => $this->total_fees_and_vat,
            'refinancing_amount' => $this->refinancing_amount,
            'increment_amount' => $this->increment_amount,
            'status' => $this->status->value,
            'disbursement_date' => $this->disbursement_date,
            'first_payment_date' => $this->first_payment_date,
            'maturity_date' => $this->maturity_date,
            'installments' => $this->whenLoaded('installments'),
            'installment_records' => $this->whenLoaded('installmentRecords'),
            'payments' => $this->whenLoaded('payments'),
            'cycles' => $this->whenLoaded('cycles'),
            'security_transactions' => $this->whenLoaded('securityTransactions'),
            'disbursement' => $this->whenLoaded('disbursement'),
            'settlement' => $this->whenLoaded('settlement'),
            'clearance' => $this->whenLoaded('clearance'),
            'default_notices' => $this->whenLoaded('defaultNotices'),
        ];
    }
}
