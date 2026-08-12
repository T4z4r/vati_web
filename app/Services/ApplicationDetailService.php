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
            'member.kyc', 'member.activeGroupMembership', 'member.nominees', 'product', 'group.loanOfficer', 'branch',
            'assessment', 'utilizations', 'approvals.user', 'groupWitnesses.member', 'guarantors',
            'documents.uploader', 'documents.verifier', 'latestCreditReview.reviewer', 'assignedCreditOfficer', 'loan',
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
            'requested_amount' => number_format((float) $application->requested_amount, 2, '.', ''),
            'recommended_amount' => $application->recommended_amount !== null ? number_format((float) $application->recommended_amount, 2, '.', '') : null,
            'duration_months' => $application->duration_months,
            'recommended_duration_months' => $application->recommended_duration_months,
            'expected_installment' => number_format($figures['total_repayment'] / $installmentCount, 2, '.', ''),
            'security_amount' => number_format($figures['security_amount'], 2, '.', ''),
            'fees' => number_format($figures['charges'], 2, '.', ''),
            'amount_receivable' => number_format($figures['amount_receivable'], 2, '.', ''),
            'loan_purpose' => $application->loan_purpose,
            'business_summary' => $application->business_summary,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'loan_officer' => $application->group->loanOfficer ? ['id' => $application->group->loanOfficer->id, 'name' => $application->group->loanOfficer->name] : null,
            'assigned_credit_officer' => $application->assignedCreditOfficer ? ['id' => $application->assignedCreditOfficer->id, 'name' => $application->assignedCreditOfficer->name] : null,
            'branch' => ['id' => $application->branch->id, 'name' => $application->branch->branch_name],
            'loan_product' => ['id' => $application->product->id, 'name' => $application->product->name],
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
                'existing_external_debt' => $this->money($application->assessment?->existing_external_debt),
                'net_disposable_income' => $this->money($application->assessment?->disposable_income),
                'debt_service_ratio' => $application->assessment?->debt_service_ratio !== null ? (float) $application->assessment->debt_service_ratio : null,
                'assessment_comment' => $application->assessment?->assessment_comment,
            ],
            'utilizations' => $application->utilizations,
            'witnesses' => $application->groupWitnesses,
            'guarantors' => $application->guarantors,
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
            'joined_at' => $member->admission_date?->toDateString(), 'status' => $member->status,
            'photo_url' => $member->photo_path ? asset('storage/'.$member->photo_path) : null,
            'kyc' => [
                'status' => $member->kyc ? 'captured' : 'incomplete',
                'physical_address' => $member->physical_address,
                'last_reviewed_at' => $member->kyc?->updated_at?->toIso8601String(),
            ],
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
