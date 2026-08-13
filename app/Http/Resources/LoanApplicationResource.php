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
            'branch' => $this->whenLoaded('branch'),
            'application_type' => $this->application_type,
            'requested_amount' => $this->requested_amount,
            'recommended_amount' => $this->recommended_amount,
            'duration_months' => $this->duration_months,
            'recommended_duration_months' => $this->recommended_duration_months,
            'risk_level' => $this->risk_level,
            'assigned_credit_officer_id' => $this->assigned_credit_officer_id,
            'existing_loan_balance' => $this->existing_loan_balance,
            'refinancing_amount' => $this->refinancing_amount,
            'increment_amount' => $this->increment_amount,
            'loan_purpose' => $this->loan_purpose,
            'business_summary' => $this->business_summary,
            'status' => $this->status?->value,
            'assessment' => $this->whenLoaded('assessment'),
            'utilizations' => $this->whenLoaded('utilizations'),
            'approvals' => $this->whenLoaded('approvals'),
            'group_witnesses' => $this->whenLoaded('groupWitnesses'),
            'loan_term' => $this->whenLoaded('term'),
            'consented_at' => $this->consented_at,
            'cancellation_deadline' => $this->cancellation_deadline,
            'applicant_signature_path' => $this->applicant_signature_path,
            'applicant_thumbprint_path' => $this->applicant_thumbprint_path,
            'guarantors' => $this->whenLoaded('guarantors'),
            'documents' => $this->whenLoaded('documents'),
            'requirements' => [
                'submission' => [
                    'attachments_required' => false,
                    'applicant_evidence_required' => false,
                    'guarantor_evidence_required' => false,
                ],
                'approval' => [
                    'attachments_required' => false,
                    'applicant_evidence_required' => true,
                    'complete_guarantors_required' => 2,
                    'confirmed_group_witnesses_required' => $this->relationLoaded('product') ? $this->product->required_group_witnesses : null,
                ],
            ],
            'nominees' => $this->when($this->relationLoaded('member') && $this->member->relationLoaded('nominees'), fn () => $this->member->nominees),
            'cancellation' => $this->whenLoaded('cancellation'),
            'witness_progress' => $this->when($this->relationLoaded('product'), fn () => [
                'required' => $this->product->required_group_witnesses,
                'confirmed' => $this->relationLoaded('groupWitnesses') ? $this->groupWitnesses->whereNotNull('confirmed_at')->count() : null,
            ]),
            'loan' => $this->whenLoaded('loan'),
            'submitted_at' => $this->submitted_at,
            'credit_review_attempt' => $this->credit_review_attempt,
        ];
    }
}
