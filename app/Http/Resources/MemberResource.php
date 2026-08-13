<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_number' => $this->membership_number,
            'full_name' => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'guardian_name' => $this->guardian_name,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'national_id' => $this->national_id,
            'voter_id' => $this->voter_id,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'occupation' => $this->occupation,
            'nationality' => $this->nationality,
            'physical_address' => $this->physical_address,
            'region' => $this->region,
            'district' => $this->district,
            'ward' => $this->ward,
            'street' => $this->street,
            // Keep joined_at for backwards compatibility with existing clients.
            'joined_at' => $this->admission_date?->toDateString(),
            'admission_date' => $this->admission_date?->toDateString(),
            'passbook_issue_date' => $this->passbook_issue_date?->toDateString(),
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'status' => $this->status,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'branch_code' => $this->branch->branch_code,
                'branch_name' => $this->branch->branch_name,
                'phone' => $this->branch->phone,
                'email' => $this->branch->email,
                'address' => $this->branch->address,
                'manager' => $this->branch->relationLoaded('manager') && $this->branch->manager
                    ? ['id' => $this->branch->manager->id, 'name' => $this->branch->manager->name]
                    : null,
            ]),
            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group->id,
                'group_code' => $this->group->group_code,
                'group_name' => $this->group->group_name,
                'meeting_day' => $this->group->meeting_day,
                'meeting_time' => $this->group->meeting_time,
                'location' => $this->group->location,
                'region' => $this->group->region,
                'district' => $this->group->district,
                'ward' => $this->group->ward,
                'loan_officer' => $this->group->relationLoaded('loanOfficer') && $this->group->loanOfficer
                    ? ['id' => $this->group->loanOfficer->id, 'name' => $this->group->loanOfficer->name]
                    : null,
            ]),
            'issued_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy
                ? ['id' => $this->createdBy->id, 'name' => $this->createdBy->name]
                : null),
            'kyc' => $this->whenLoaded('kyc'),
            'active_group_membership' => $this->whenLoaded('activeGroupMembership'),
            'nominees' => $this->whenLoaded('nominees'),
            'family_members' => $this->whenLoaded('familyMembers'),
            'assets' => $this->whenLoaded('assets', fn () => $this->assets->map(fn ($asset) => [
                'id' => $asset->id,
                'name' => $asset->assetType?->name,
                'category' => $asset->assetType?->category,
                'quantity' => $asset->quantity,
                'estimated_value' => $this->money($asset->estimated_value),
                'description' => $asset->description,
            ])->values()),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_type_label' => $document->getDocumentTypeLabel(),
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'description' => $document->description,
                'file_url' => asset('storage/'.$document->file_path),
                'uploaded_by' => $document->relationLoaded('uploadedBy') && $document->uploadedBy
                    ? ['id' => $document->uploadedBy->id, 'name' => $document->uploadedBy->name]
                    : null,
                'uploaded_at' => $document->created_at?->toIso8601String(),
            ])->values()),
            'security_account' => $this->whenLoaded('securityAccount', fn () => $this->securityAccount ? [
                'id' => $this->securityAccount->id,
                'balance' => $this->money($this->securityAccount->balance),
                'transactions' => $this->securityAccount->relationLoaded('transactions')
                    ? $this->securityAccount->transactions->sortByDesc('transaction_date')->values()
                    : [],
            ] : null),
            'passbook_replacements' => $this->whenLoaded('passbookReplacements'),
            'loan_applications' => $this->whenLoaded('loanApplications', fn () => $this->loanApplications->sortByDesc('created_at')->map(fn ($application) => [
                'id' => $application->id,
                'application_number' => $application->application_number,
                'application_type' => $application->application_type,
                'loan_product' => $application->relationLoaded('product') && $application->product
                    ? ['id' => $application->product->id, 'name' => $application->product->name, 'code' => $application->product->code]
                    : null,
                'requested_amount' => $this->money($application->requested_amount),
                'recommended_amount' => $application->recommended_amount !== null ? $this->money($application->recommended_amount) : null,
                'duration_months' => $application->duration_months,
                'recommended_duration_months' => $application->recommended_duration_months,
                'existing_loan_balance' => $this->money($application->existing_loan_balance),
                'refinancing_amount' => $this->money($application->refinancing_amount),
                'increment_amount' => $this->money($application->increment_amount),
                'loan_purpose' => $application->loan_purpose,
                'business_summary' => $application->business_summary,
                'status' => $application->status?->value,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'loan_id' => $application->relationLoaded('loan') ? $application->loan?->id : null,
            ])->values()),
            'loans' => $this->whenLoaded('loans', fn () => $this->loans->sortByDesc('created_at')->map(fn ($loan) => $this->loan($loan))->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function loan($loan): array
    {
        $paidAmount = max(0, (float) $loan->total_repayment - (float) $loan->total_balance);

        return [
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'loan_product' => $loan->relationLoaded('product') && $loan->product
                ? ['id' => $loan->product->id, 'name' => $loan->product->name, 'code' => $loan->product->code]
                : null,
            'loan_cycle' => $loan->loan_cycle,
            'business_name' => $loan->business_name,
            'interest_rate' => $loan->interest_rate,
            'principal_amount' => $this->money($loan->principal_amount),
            'adjusted_principal_amount' => $this->money($loan->adjusted_principal_amount ?? $loan->principal_amount),
            'interest_amount' => $this->money($loan->interest_amount),
            'total_repayment' => $this->money($loan->total_repayment),
            'amount_paid' => $this->money($paidAmount),
            'principal_balance' => $this->money($loan->principal_balance),
            'interest_balance' => $this->money($loan->interest_balance),
            'total_balance' => $this->money($loan->total_balance),
            'admission_fee' => $this->money($loan->admission_fee),
            'processing_fee' => $this->money($loan->processing_fee),
            'transaction_charges' => $this->money($loan->transaction_charges),
            'other_charges' => $this->money($loan->other_charges),
            'total_fees_and_vat' => $this->money($loan->total_fees_and_vat),
            'refinancing_amount' => $this->money($loan->refinancing_amount),
            'increment_amount' => $this->money($loan->increment_amount),
            'weekly_installment' => $this->money($loan->weekly_installment ?: $loan->installment_amount),
            'installment_amount' => $this->money($loan->installment_amount),
            'number_of_installments' => $loan->number_of_installments,
            'disbursement_date' => $loan->disbursement_date?->toDateString(),
            'first_payment_date' => $loan->first_payment_date?->toDateString(),
            'maturity_date' => $loan->maturity_date?->toDateString(),
            'status' => $loan->status?->value,
            'application' => $loan->relationLoaded('application') && $loan->application ? [
                'id' => $loan->application->id,
                'application_number' => $loan->application->application_number,
                'application_type' => $loan->application->application_type,
                'loan_purpose' => $loan->application->loan_purpose,
                'business_summary' => $loan->application->business_summary,
                'applicant_signature_captured' => filled($loan->application->applicant_signature_path),
                'applicant_thumbprint_captured' => filled($loan->application->applicant_thumbprint_path),
                'guarantors' => $loan->application->relationLoaded('guarantors')
                    ? $loan->application->guarantors->map(fn ($guarantor) => [
                        'id' => $guarantor->id,
                        'guarantor_type' => $guarantor->guarantor_type,
                        'name' => $guarantor->name,
                        'relationship' => $guarantor->relationship,
                        'phone' => $guarantor->phone,
                        'national_id' => $guarantor->national_id,
                        'voter_id' => $guarantor->voter_id,
                        'house_number' => $guarantor->house_number,
                        'street' => $guarantor->street,
                        'ward' => $guarantor->ward,
                        'district' => $guarantor->district,
                        'region' => $guarantor->region,
                        'business_address' => $guarantor->business_address,
                        'signature_captured' => filled($guarantor->signature_path),
                        'thumbprint_captured' => filled($guarantor->thumbprint_path),
                        'joint_photo_captured' => filled($guarantor->joint_photo_path),
                        'declaration_accepted_at' => $guarantor->declaration_accepted_at?->toIso8601String(),
                    ])->values()
                    : [],
            ] : null,
            'cycles' => $loan->relationLoaded('cycles') ? $loan->cycles->map(fn ($cycle) => [
                'id' => $cycle->id,
                'cycle_type' => $cycle->cycle_type,
                'is_main_cycle' => $cycle->is_main_cycle,
                'is_refinancing_cycle' => $cycle->is_refinancing_cycle,
                'business_name' => $cycle->business_name,
                'principal_amount' => $this->money($cycle->principal_amount),
                'adjusted_principal_amount' => $this->money($cycle->adjusted_principal_amount),
                'interest_rate' => $cycle->interest_rate,
                'disbursement_date' => $cycle->disbursement_date?->toDateString(),
                'first_payment_date' => $cycle->first_payment_date?->toDateString(),
                'admission_fee' => $this->money($cycle->admission_fee),
                'processing_fee' => $this->money($cycle->processing_fee),
                'transaction_charges' => $this->money($cycle->transaction_charges),
                'other_charges' => $this->money($cycle->other_charges),
                'vat_amount' => $this->money($cycle->vat_amount),
                'total_fees_and_vat' => $this->money($cycle->total_fees_and_vat),
                'increment_amount' => $this->money($cycle->increment_amount),
                'refinancing_amount' => $this->money($cycle->refinancing_amount),
                'total_with_interest' => $this->money($cycle->total_with_interest),
                'weekly_installment' => $this->money($cycle->weekly_installment),
                'total_installments' => $cycle->total_installments,
                'status' => $cycle->status,
                'notes' => $cycle->notes,
            ])->values() : [],
            'installments' => $loan->relationLoaded('installments') ? $loan->installments->sortBy('installment_number')->map(fn ($installment) => [
                'id' => $installment->id,
                'installment_number' => $installment->installment_number,
                'due_date' => $installment->due_date?->toDateString(),
                'principal_due' => $this->money($installment->principal_due),
                'interest_due' => $this->money($installment->interest_due),
                'total_due' => $this->money($installment->total_due),
                'principal_paid' => $this->money($installment->principal_paid),
                'interest_paid' => $this->money($installment->interest_paid),
                'total_paid' => $this->money($installment->total_paid),
                'interest_exemption' => $this->money($installment->interest_exemption),
                'outstanding_balance' => $this->money($installment->outstanding_balance),
                'status' => $installment->status,
            ])->values() : [],
            'installment_records' => $loan->relationLoaded('installmentRecords')
                ? $loan->installmentRecords->sortBy('installment_number')->map(fn ($record) => [
                    'id' => $record->id,
                    'loan_cycle_id' => $record->loan_cycle_id,
                    'installment_number' => $record->installment_number,
                    'payment_date' => $record->payment_date?->toDateString(),
                    'actual_payment_date' => $record->actual_payment_date?->toDateString(),
                    'principal_amount' => $this->money($record->principal_amount),
                    'interest_amount' => $this->money($record->interest_amount),
                    'total_amount' => $this->money($record->total_amount),
                    'interest_exemption' => $this->money($record->interest_exemption),
                    'outstanding_balance' => $this->money($record->outstanding_balance),
                    'is_paid' => $record->is_paid,
                    'status' => $record->status_badge,
                    'remarks' => $record->remarks,
                    'collector_notes' => $record->collector_notes,
                    'collector' => $record->relationLoaded('collector') && $record->collector
                        ? ['id' => $record->collector->id, 'name' => $record->collector->name]
                        : null,
                    'collector_signature_captured' => filled($record->collector_signature),
                    'branch_manager_signature_captured' => filled($record->branch_manager_signature),
                ])->values()
                : [],
            'payments' => $loan->relationLoaded('payments') ? $loan->payments->sortByDesc('paid_at')->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $this->money($payment->amount),
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'external_reference' => $payment->external_reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'status' => $payment->status,
                'remarks' => $payment->remarks,
            ])->values() : [],
            'security_transactions' => $loan->relationLoaded('securityTransactions')
                ? $loan->securityTransactions->sortByDesc('transaction_date')->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'transaction_date' => $transaction->transaction_date?->toDateString(),
                    'security_amount' => $this->money($transaction->security_amount),
                    'withdrawal_amount' => $this->money($transaction->withdrawal_amount),
                    'balance' => $this->money($transaction->balance),
                    'collected_by' => $transaction->relationLoaded('collectedBy') && $transaction->collectedBy
                        ? ['id' => $transaction->collectedBy->id, 'name' => $transaction->collectedBy->name]
                        : null,
                    'approved_by' => $transaction->relationLoaded('approvedBy') && $transaction->approvedBy
                        ? ['id' => $transaction->approvedBy->id, 'name' => $transaction->approvedBy->name]
                        : null,
                ])->values()
                : [],
            'disbursement' => $loan->relationLoaded('disbursement') ? $loan->disbursement : null,
            'settlement' => $loan->relationLoaded('settlement') ? $loan->settlement : null,
            'clearance' => $loan->relationLoaded('clearance') ? $loan->clearance : null,
            'default_notices' => $loan->relationLoaded('defaultNotices') ? $loan->defaultNotices : [],
            'created_at' => $loan->created_at?->toIso8601String(),
        ];
    }

    private function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
