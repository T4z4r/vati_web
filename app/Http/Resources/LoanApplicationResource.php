<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'member' => new MemberResource($this->whenLoaded('member')),
            'product' => $this->whenLoaded('product'),
            'group' => $this->whenLoaded('group'),
            'requested_amount' => $this->requested_amount,
            'duration_months' => $this->duration_months,
            'status' => $this->status?->value,
            'assessment' => $this->whenLoaded('assessment'),
            'approvals' => $this->whenLoaded('approvals'),
            'group_witnesses' => $this->whenLoaded('groupWitnesses'),
            'witness_progress' => $this->when($this->relationLoaded('product'), fn () => [
                'required' => $this->product->required_group_witnesses,
                'confirmed' => $this->relationLoaded('groupWitnesses') ? $this->groupWitnesses->whereNotNull('confirmed_at')->count() : null,
            ]),
            'loan' => $this->whenLoaded('loan'),
            'submitted_at' => $this->submitted_at,
        ];
    }
}
