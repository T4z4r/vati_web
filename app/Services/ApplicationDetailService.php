<?php

namespace App\Services;

use App\Models\GroupAttendance;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\Payment;
use Spatie\Activitylog\Models\Activity;

class ApplicationDetailService
{
    public function __construct(private LoanCalculatorService $calculator) {}

    public function build(LoanApplication $application): array
    {
        $application->load([
            'member.kyc', 'member.activeGroupMembership', 'member.nominees', 'member.familyMembers',
            'member.assets.assetType', 'member.branch.area.region', 'product', 'group.loanOfficer', 'branch.area.region',
            'assessment', 'utilizations', 'approvals.user', 'groupWitnesses.member', 'guarantors',
            'documents.uploader', 'documents.verifier', 'latestCreditReview.reviewer', 'assignedCreditOfficer',
            'loan.disbursement', 'term',
        ]);
        $amount = (float) ($application->recommended_amount ?: $application->requested_amount);
        $duration = (int) ($application->recommended_duration_months ?: $application->duration_months);
        $figures = $this->calculator->calculate($application->product, $amount, $duration);
        $installmentCount = $application->product->repayment_frequency === 'weekly' ? max(1, (int) round($duration * 52 / 12)) : $duration;
        $groupMetrics = $this->groupMetrics($application);

        return [
            'id' => $application->id,
            'application_number' => $application->application_number,
            'application_type' => $application->application_type,
            'status' => $application->status->value,
            'risk_level' => $application->risk_level,
            'application_date' => $application->created_at?->toDateString(),
            'disbursement_date' => $application->loan?->disbursement_date?->toDateString(),
            'requested_amount' => number_format((float) $application->requested_amount, 2, '.', ''),
            'recommended_amount' => $application->recommended_amount !== null ? number_format((float) $application->recommended_amount, 2, '.', '') : null,
            'existing_loan_balance' => $this->money($application->existing_loan_balance),
            'refinancing_amount' => $this->money($application->refinancing_amount),
            'increment_amount' => $this->money($application->increment_amount),
            'duration_months' => $application->duration_months,
            'recommended_duration_months' => $application->recommended_duration_months,
            'installment_count' => $installmentCount,
            'expected_installment' => number_format($figures['total_repayment'] / $installmentCount, 2, '.', ''),
            'interest_amount' => $this->money($figures['interest']),
            'total_repayment' => $this->money($figures['total_repayment']),
            'security_amount' => number_format($figures['security_amount'], 2, '.', ''),
            'fees' => number_format($figures['charges'], 2, '.', ''),
            'amount_receivable' => number_format($figures['amount_receivable'], 2, '.', ''),
            'processing_fee' => $this->money($figures['processing_fee']),
            'processing_fee_vat' => $this->money($figures['processing_fee_vat']),
            'transaction_fee' => $this->money($figures['transaction_fee']),
            'transaction_fee_vat' => $this->money($figures['transaction_fee_vat']),
            'membership_fee' => $this->money($figures['membership_fee']),
            'loan_purpose' => $application->loan_purpose,
            'business_summary' => $application->business_summary,
            'consent_declaration' => $application->consent_declaration ?: $application->term?->body,
            'consented_at' => $application->consented_at?->toIso8601String(),
            'cancellation_deadline' => $application->cancellation_deadline?->toIso8601String(),
            'applicant_signature_captured' => filled($application->applicant_signature_path),
            'applicant_thumbprint_captured' => filled($application->applicant_thumbprint_path),
            'submitted_at' => $application->submitted_at?->toIso8601String(),
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
                    'confirmed_group_witnesses_required' => $application->product->required_group_witnesses,
                ],
            ],
            'loan_officer' => $application->group->loanOfficer ? ['id' => $application->group->loanOfficer->id, 'name' => $application->group->loanOfficer->name] : null,
            'assigned_credit_officer' => $application->assignedCreditOfficer ? ['id' => $application->assignedCreditOfficer->id, 'name' => $application->assignedCreditOfficer->name] : null,
            'branch' => [
                'id' => $application->branch->id,
                'name' => $application->branch->branch_name,
                'area' => $application->branch->area?->name,
                'region' => $application->branch->area?->region?->name,
            ],
            'loan_product' => [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'annual_interest_rate' => (float) $application->product->annual_interest_rate,
                'repayment_frequency' => $application->product->repayment_frequency,
                'security_percentage' => (float) $application->product->security_percentage,
                'vat_percentage' => (float) $application->product->vat_percentage,
            ],
            'member' => $this->member($application),
            'group' => [...$groupMetrics, 'id' => $application->group->id, 'group_code' => $application->group->group_code, 'group_name' => $application->group->group_name],
            'assessment' => [
                'business_name' => $application->member->kyc?->business_name,
                'business_type' => $application->member->kyc?->business_type,
                'business_location' => $application->member->kyc?->business_address,
                'core_business_income' => $this->money($application->assessment?->core_business_income),
                'other_income' => $this->money($application->assessment?->other_income),
                'business_expenses' => $this->money($application->assessment?->business_expenses),
                'household_expenses' => $this->money($application->assessment?->household_expenses),
                'monthly_profit' => $this->money($application->assessment?->monthly_profit),
                'existing_external_debt' => $this->money($application->assessment?->existing_external_debt),
                'net_disposable_income' => $this->money($application->assessment?->disposable_income),
                'debt_service_ratio' => $application->assessment?->debt_service_ratio !== null ? (float) $application->assessment->debt_service_ratio : null,
                'assessment_comment' => $application->assessment?->assessment_comment,
            ],
            'utilizations' => $application->utilizations->map(fn ($item) => [
                'purpose' => $item->purpose,
                'allocation_amount' => $this->money($item->allocation_amount),
                'current_asset_value' => $this->money($item->current_asset_value),
            ])->values(),
            'witnesses' => $application->groupWitnesses->map(fn ($witness) => [
                'name' => trim("{$witness->member->first_name} {$witness->member->middle_name} {$witness->member->last_name}"),
                'phone' => $witness->member->phone,
                'confirmed_at' => $witness->confirmed_at?->toIso8601String(),
                'signature_captured' => filled($witness->signature_path),
            ])->values(),
            'guarantors' => $application->guarantors->map(fn ($guarantor) => [
                'type' => $guarantor->guarantor_type,
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
            ])->values(),
            'approvals' => $application->approvals->map(fn ($approval) => [
                'role' => $approval->role,
                'decision' => $approval->decision,
                'name' => $approval->user?->name,
                'remarks' => $approval->remarks,
                'acted_at' => $approval->acted_at?->toIso8601String(),
            ])->values(),
            'documents' => $application->documents->map(fn ($document) => $this->document($application, $document))->values(),
            'credit_review' => $application->latestCreditReview,
            'risk_signals' => $this->riskSignals($application, (float) ($figures['total_repayment'] / $installmentCount)),
            'history' => $this->history($application),
        ];
    }

    private function member(LoanApplication $application): array
    {
        $member = $application->member;

        return [
            'id' => $member->id, 'member_number' => $member->membership_number,
            'full_name' => trim("{$member->first_name} {$member->middle_name} {$member->last_name}"),
            'phone' => $member->phone, 'national_id' => $member->national_id,
            'voter_id' => $member->voter_id,
            'guardian_name' => $member->guardian_name,
            'occupation' => $member->occupation,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'age' => $member->date_of_birth?->age,
            'gender' => $member->gender,
            'marital_status' => $member->marital_status,
            'nationality' => $member->nationality,
            'alternate_phone' => $member->alternate_phone,
            'physical_address' => $member->physical_address,
            'region' => $member->region,
            'district' => $member->district,
            'ward' => $member->ward,
            'street' => $member->street,
            'joined_at' => $member->admission_date?->toDateString(), 'status' => $member->status,
            'photo_url' => $member->photo_path ? asset('storage/'.$member->photo_path) : null,
            'kyc' => [
                'status' => $member->kyc ? 'captured' : 'incomplete',
                'physical_address' => $member->physical_address,
                'mpesa_phone' => $member->kyc?->mpesa_phone,
                'bank_account_number' => $member->kyc?->bank_account_number,
                'bank_account_name' => $member->kyc?->bank_account_name,
                'bank_name' => $member->kyc?->bank_name,
                'house_number' => $member->kyc?->house_number,
                'police_station' => $member->kyc?->police_station,
                'business_name' => $member->kyc?->business_name,
                'business_type' => $member->kyc?->business_type,
                'business_address' => $member->kyc?->business_address,
                'household_monthly_income' => $this->money($member->kyc?->household_monthly_income),
                'household_monthly_expenses' => $this->money($member->kyc?->household_monthly_expenses),
                'number_of_dependants' => $member->kyc?->number_of_dependants ?? 0,
                'head_of_household' => $member->kyc?->head_of_household,
                'house_ownership_status' => $member->kyc?->house_ownership_status,
                'house_roof_type' => $member->kyc?->house_roof_type,
                'house_fence_type' => $member->kyc?->house_fence_type,
                'last_reviewed_at' => $member->kyc?->updated_at?->toIso8601String(),
            ],
            'family_members' => $member->familyMembers->map(fn ($family) => [
                'name' => $family->name,
                'gender' => $family->gender,
                'age' => $family->age,
                'relationship' => $family->relationship,
                'education' => $family->education,
                'marital_status' => $family->marital_status,
                'occupation' => $family->occupation,
                'secondary_occupation' => $family->secondary_occupation,
            ])->values(),
            'assets' => $member->assets->map(fn ($asset) => [
                'name' => $asset->assetType?->name,
                'category' => $asset->assetType?->category,
                'quantity' => $asset->quantity,
                'estimated_value' => $this->money($asset->estimated_value),
                'description' => $asset->description,
            ])->values(),
            'nominees' => $member->nominees->map(fn ($nominee) => [
                'name' => $nominee->name,
                'relationship' => $nominee->relationship,
                'percentage' => (float) $nominee->percentage,
                'attested_at' => $nominee->attested_at?->toIso8601String(),
                'signature_captured' => filled($nominee->signature_path),
            ])->values(),
        ];
    }

    private function groupMetrics(LoanApplication $application): array
    {
        $group = $application->group;
        $attendanceTotal = GroupAttendance::whereHas('meeting', fn ($query) => $query->where('group_id', $group->id))->count();
        $attendancePresent = GroupAttendance::whereHas('meeting', fn ($query) => $query->where('group_id', $group->id))->where('status', 'present')->count();
        $loanIds = Loan::where('group_id', $group->id)->pluck('id');
        $expected = (float) LoanInstallment::whereIn('loan_id', $loanIds)->whereDate('due_date', '<=', today())->sum('total_due');
        $paid = (float) Payment::whereIn('loan_id', $loanIds)->where('status', 'posted')->sum('amount');

        return [
            'membership_status' => $application->member->activeGroupMembership?->status,
            'member_joined_at' => $application->member->activeGroupMembership?->joined_at?->toDateString(),
            'attendance_rate' => $attendanceTotal > 0 ? round($attendancePresent / $attendanceTotal * 100, 2) : 0,
            'repayment_rate' => $expected > 0 ? round(min(100, $paid / $expected * 100), 2) : 0,
            'active_member_count' => $group->members()->where('status', 'active')->count(),
            'loans_in_arrears' => $group->loans()->whereIn('status', ['active', 'overdue'])->whereHas('installments', fn ($query) => $query->whereDate('due_date', '<', today())->whereNotIn('status', ['paid', 'waived']))->count(),
        ];
    }

    private function document(LoanApplication $application, $document): array
    {
        return [
            'id' => $document->id, 'document_type' => $document->document_type,
            'file_name' => $document->original_name ?: basename($document->file_path),
            'mime_type' => $document->mime_type, 'size_bytes' => $document->size_bytes,
            'status' => $document->verification_status, 'remarks' => $document->remarks,
            'download_url' => route('api.loan-applications.documents.download', [$application, $document]),
            'uploaded_by' => $document->uploader ? ['id' => $document->uploader->id, 'name' => $document->uploader->name] : null,
            'uploaded_at' => $document->created_at?->toIso8601String(),
            'verified_by' => $document->verifier ? ['id' => $document->verifier->id, 'name' => $document->verifier->name] : null,
            'verified_at' => $document->verified_at?->toIso8601String(),
        ];
    }

    private function riskSignals(LoanApplication $application, float $installment): array
    {
        $signals = [];
        $disposable = (float) ($application->assessment?->disposable_income ?? 0);
        $dsr = (float) ($application->assessment?->debt_service_ratio ?? 0);
        if ($disposable < $installment) {
            $signals[] = ['code' => 'insufficient_disposable_income', 'severity' => 'critical', 'title' => 'Insufficient disposable income', 'detail' => 'Estimated installment exceeds net disposable income.'];
        }
        if ($dsr > 40) {
            $signals[] = ['code' => 'high_debt_service_ratio', 'severity' => $dsr > 60 ? 'critical' : 'warning', 'title' => 'High debt-service ratio', 'detail' => "Debt-service ratio is {$dsr}%."];
        }
        if ((float) ($application->assessment?->existing_external_debt ?? 0) > 0) {
            $signals[] = ['code' => 'external_debt', 'severity' => 'warning', 'title' => 'External debt declared', 'detail' => 'The affordability review includes debt outside VATI.'];
        }
        if ($application->documents->where('is_required', true)->contains(fn ($document) => $document->verification_status !== 'verified')) {
            $signals[] = ['code' => 'unverified_documents', 'severity' => 'warning', 'title' => 'Documents require verification', 'detail' => 'One or more required documents have not been verified.'];
        }

        return $signals;
    }

    private function history(LoanApplication $application): array
    {
        return Activity::query()->where('subject_type', LoanApplication::class)->where('subject_id', $application->id)->with('causer')->oldest()->get()->map(fn ($activity) => [
            'id' => $activity->id,
            'event' => str($activity->description)->slug('_')->toString(),
            'title' => $activity->description,
            'remarks' => $activity->properties['remarks'] ?? null,
            'actor' => $activity->causer ? ['id' => $activity->causer->id, 'name' => $activity->causer->name, 'role' => method_exists($activity->causer, 'getRoleNames') ? str($activity->causer->getRoleNames()->first())->replace('_', ' ')->title()->toString() : null] : null,
            'created_at' => $activity->created_at?->toIso8601String(),
        ])->all();
    }

    private function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
