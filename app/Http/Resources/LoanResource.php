<?php

namespace App\Http\Resources;

use App\Services\LoanCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidInstallments = $this->whenLoaded('installments', fn () => $this->installments->where('status', 'paid')->count());
        $repaymentProgress = $this->total_repayment > 0
            ? max(0, 100 - ($this->total_balance / $this->total_repayment) * 100)
            : 0;

        $breakdown = null;
        if ($this->calc_charges !== null) {
            $breakdown = [
                'principal' => number_format((float) $this->principal_amount, 2, '.', ''),
                'interest' => number_format((float) $this->interest_amount, 2, '.', ''),
                'processing_fee' => number_format((float) $this->processing_fee, 2, '.', ''),
                'processing_fee_vat' => number_format((float) $this->calc_processing_fee_vat, 2, '.', ''),
                'transaction_fee' => number_format((float) $this->transaction_charges, 2, '.', ''),
                'transaction_fee_vat' => number_format((float) $this->calc_transaction_fee_vat, 2, '.', ''),
                'membership_fee' => number_format((float) $this->calc_membership_fee ?? $this->other_charges, 2, '.', ''),
                'security_amount' => number_format((float) $this->calc_security_amount ?? 0, 2, '.', ''),
                'charges' => number_format((float) $this->calc_charges ?? $this->total_fees_and_vat, 2, '.', ''),
                'amount_receivable' => number_format((float) $this->calc_amount_receivable ?? 0, 2, '.', ''),
                'total_repayment' => number_format((float) $this->total_repayment, 2, '.', ''),
            ];
        } elseif ($this->relationLoaded('product') && $this->product && $this->principal_amount && $this->number_of_installments) {
            try {
                $breakdown = collect(app(LoanCalculatorService::class)->calculate($this->product, (float) $this->principal_amount, (int) $this->number_of_installments))
                    ->map(fn ($v) => number_format($v, 2, '.', ''))->all();
            } catch (\Throwable) {}
        }

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
            'total_repayment' => $this->total_repayment,
            'principal_balance' => $this->principal_balance,
            'total_balance' => $this->total_balance,
            'number_of_installments' => $this->number_of_installments,
            'installment_amount' => $this->installment_amount,
            'weekly_installment' => $this->weekly_installment,
            'admission_fee' => $this->admission_fee,
            'processing_fee' => $this->processing_fee,
            'other_charges' => $this->other_charges,
            'total_fees_and_vat' => $this->total_fees_and_vat,
            'refinancing_amount' => $this->refinancing_amount,
            'increment_amount' => $this->increment_amount,
            'status' => $this->status->value,
            'calculator_breakdown' => $breakdown,
            'repayment_progress' => round($repaymentProgress, 2),
            'paid_installments' => $paidInstallments,
            'disbursement_date' => $this->disbursement_date?->toDateString(),
            'first_payment_date' => $this->first_payment_date?->toDateString(),
            'maturity_date' => $this->maturity_date?->toDateString(),
            'installments' => $this->whenLoaded('installments', fn () => $this->installments->sortBy('installment_number')->values()->map(fn ($i) => [
                'id' => $i->id,
                'installment_number' => $i->installment_number,
                'due_date' => $i->due_date?->toDateString(),
                'principal_due' => $i->principal_due,
                'interest_due' => $i->interest_due,
                'total_due' => $i->total_due,
                'principal_paid' => $i->principal_paid,
                'interest_paid' => $i->interest_paid,
                'total_paid' => $i->total_paid,
                'interest_exemption' => $i->interest_exemption,
                'balance' => max(0, (float) $i->total_due - (float) $i->total_paid - (float) $i->interest_exemption),
                'status' => $i->status,
            ])->all()),
            'installment_records' => $this->whenLoaded('installmentRecords', fn () => $this->installmentRecords->sortBy('installment_number')->values()->map(fn ($r) => [
                'id' => $r->id,
                'loan_cycle_id' => $r->loan_cycle_id,
                'installment_number' => $r->installment_number,
                'payment_date' => $r->payment_date?->toDateString(),
                'actual_payment_date' => $r->actual_payment_date?->toDateString(),
                'principal_amount' => $r->principal_amount,
                'interest_amount' => $r->interest_amount,
                'total_amount' => $r->total_amount,
                'interest_exemption' => $r->interest_exemption,
                'outstanding_balance' => $r->outstanding_balance,
                'is_paid' => $r->is_paid,
                'status' => $r->status_badge,
                'remarks' => $r->remarks,
                'collector_notes' => $r->collector_notes,
                'collector' => $r->relationLoaded('collector') && $r->collector
                    ? ['id' => $r->collector->id, 'name' => $r->collector->name]
                    : null,
                'collector_signature_captured' => filled($r->collector_signature),
                'branch_manager_signature_captured' => filled($r->branch_manager_signature),
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->sortByDesc('paid_at')->values()->map(fn ($p) => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'amount' => $p->amount,
                'payment_method' => $p->payment_method,
                'reference_number' => $p->reference_number,
                'external_reference' => $p->external_reference,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'status' => $p->status,
                'remarks' => $p->remarks,
                'reversed_at' => $p->reversed_at?->toIso8601String(),
                'allocations' => $p->relationLoaded('allocations') ? $p->allocations->map(fn ($a) => [
                    'id' => $a->id,
                    'loan_installment_id' => $a->loan_installment_id,
                    'amount' => $a->amount,
                    'principal_portion' => $a->principal_portion,
                    'interest_portion' => $a->interest_portion,
                ])->values()->all() : [],
            ])->all()),
            'cycles' => $this->whenLoaded('cycles', fn () => $this->cycles->map(fn ($c) => [
                'id' => $c->id,
                'cycle_type' => $c->cycle_type,
                'is_main_cycle' => $c->is_main_cycle,
                'is_refinancing_cycle' => $c->is_refinancing_cycle,
                'business_name' => $c->business_name,
                'principal_amount' => $c->principal_amount,
                'adjusted_principal_amount' => $c->adjusted_principal_amount,
                'interest_rate' => $c->interest_rate,
                'disbursement_date' => $c->disbursement_date?->toDateString(),
                'first_payment_date' => $c->first_payment_date?->toDateString(),
                'admission_fee' => $c->admission_fee,
                'processing_fee' => $c->processing_fee,
                'transaction_charges' => $c->transaction_charges,
                'other_charges' => $c->other_charges,
                'vat_amount' => $c->vat_amount,
                'total_fees_and_vat' => $c->total_fees_and_vat,
                'increment_amount' => $c->increment_amount,
                'refinancing_amount' => $c->refinancing_amount,
                'total_with_interest' => $c->total_with_interest,
                'weekly_installment' => $c->weekly_installment,
                'total_installments' => $c->total_installments,
                'status' => $c->status,
                'notes' => $c->notes,
            ])->values()->all()),
            'security_transactions' => $this->whenLoaded('securityTransactions', fn () => $this->securityTransactions->sortByDesc('transaction_date')->values()->map(fn ($t) => [
                'id' => $t->id,
                'transaction_date' => $t->transaction_date?->toDateString(),
                'security_amount' => $t->security_amount,
                'withdrawal_amount' => $t->withdrawal_amount,
                'balance' => $t->balance,
                'collected_by' => $t->relationLoaded('collectedBy') && $t->collectedBy
                    ? ['id' => $t->collectedBy->id, 'name' => $t->collectedBy->name]
                    : null,
                'approved_by' => $t->relationLoaded('approvedBy') && $t->approvedBy
                    ? ['id' => $t->approvedBy->id, 'name' => $t->approvedBy->name]
                    : null,
            ])->all()),
            'disbursement' => $this->whenLoaded('disbursement', fn () => [
                'id' => $this->disbursement->id,
                'method' => $this->disbursement->method,
                'recipient_number' => $this->disbursement->recipient_number,
                'reference_number' => $this->disbursement->reference_number,
                'disbursed_at' => $this->disbursement->disbursed_at?->toIso8601String(),
            ]),
            'settlement' => $this->whenLoaded('settlement', fn () => [
                'id' => $this->settlement->id,
                'cash_payment' => $this->settlement->cash_payment,
                'security_offset' => $this->settlement->security_offset,
                'interest_waived' => $this->settlement->interest_waived,
                'security_refund' => $this->settlement->security_refund,
                'settlement_date' => $this->settlement->settlement_date?->toDateString(),
                'approved_at' => $this->settlement->approved_at?->toIso8601String(),
            ]),
            'clearance' => $this->whenLoaded('clearance', fn () => [
                'id' => $this->clearance->id,
                'status' => $this->clearance->status,
                'authorized_at' => $this->clearance->authorized_at?->toIso8601String(),
                'manager_signature_path' => $this->clearance->manager_signature_path,
                'comments' => $this->clearance->comments,
            ]),
            'default_notices' => $this->whenLoaded('defaultNotices', fn () => $this->defaultNotices->map(fn ($n) => [
                'id' => $n->id,
                'delivery_method' => $n->delivery_method,
                'delivery_reference' => $n->delivery_reference,
                'issued_at' => $n->issued_at?->toIso8601String(),
                'expires_at' => $n->expires_at?->toIso8601String(),
                'acknowledged_at' => $n->acknowledged_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
