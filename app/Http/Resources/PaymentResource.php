<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'external_reference' => $this->external_reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'membership_number' => $this->member->membership_number,
                'full_name' => collect([$this->member->first_name, $this->member->middle_name, $this->member->last_name])->filter()->implode(' '),
                'phone' => $this->member->phone,
            ]),
            'loan' => $this->whenLoaded('loan', fn () => [
                'id' => $this->loan->id,
                'loan_number' => $this->loan->loan_number,
                'loan_cycle' => $this->loan->loan_cycle,
                'status' => $this->loan->status->value,
                'group_id' => $this->loan->group_id,
                'loan_product_id' => $this->loan->loan_product_id,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'collected_by' => $this->whenLoaded('collectedBy', fn () => $this->collectedBy ? [
                'id' => $this->collectedBy->id,
                'name' => $this->collectedBy->name,
            ] : null),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'loan_installment_id' => $allocation->loan_installment_id,
                'installment_number' => $allocation->relationLoaded('installment') ? $allocation->installment?->installment_number : null,
                'due_date' => $allocation->relationLoaded('installment') ? $allocation->installment?->due_date?->toDateString() : null,
                'principal_amount' => number_format((float) $allocation->principal_amount, 2, '.', ''),
                'interest_amount' => number_format((float) $allocation->interest_amount, 2, '.', ''),
                'penalty_amount' => number_format((float) $allocation->penalty_amount, 2, '.', ''),
                'total_amount' => number_format((float) $allocation->principal_amount + (float) $allocation->interest_amount + (float) $allocation->penalty_amount, 2, '.', ''),
            ])->values()->all()),
            'allocation_totals' => $this->whenLoaded('allocations', fn () => [
                'principal' => number_format((float) $this->allocations->sum('principal_amount'), 2, '.', ''),
                'interest' => number_format((float) $this->allocations->sum('interest_amount'), 2, '.', ''),
                'penalty' => number_format((float) $this->allocations->sum('penalty_amount'), 2, '.', ''),
            ]),
            'sync_status' => $this->sync_status,
            'device_id' => $this->device_id,
            'remarks' => $this->remarks,
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversal_reason' => $this->reversal_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
