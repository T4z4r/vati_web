<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanClearance;
use App\Models\LoanDefaultNotice;
use App\Models\Member;
use App\Models\PassbookReplacement;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;

class LoanAdministrationService
{
    public function replacePassbook(Member $member, User $user, array $data): PassbookReplacement
    {
        if (empty($data['payment_reference'])) {
            throw new DomainException('The TZS 1,000 duplicate-passbook charge must be paid before issue.');
        }

        $replacement = $member->passbookReplacements()->create([
            'reason' => $data['reason'],
            'fee_amount' => 1000,
            'payment_status' => 'paid',
            'payment_reference' => $data['payment_reference'],
            'paid_at' => now(),
            'issued_at' => now(),
            'issued_by' => $user->id,
            'remarks' => $data['remarks'] ?? null,
        ]);
        activity()->causedBy($user)->performedOn($member)->withProperties(['fee' => 1000])->log('Duplicate passbook issued');

        return $replacement;
    }

    public function issueDefaultNotice(Loan $loan, User $user, array $data): LoanDefaultNotice
    {
        if (! in_array($loan->status, [LoanStatus::ACTIVE, LoanStatus::OVERDUE], true) || (float) $loan->total_balance <= 0) {
            throw new DomainException('A default notice requires an active outstanding debt.');
        }

        $issuedAt = now();
        $notice = $loan->defaultNotices()->create([
            'notice_days' => 14,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addDays(14),
            'delivery_method' => $data['delivery_method'],
            'delivery_reference' => $data['delivery_reference'] ?? null,
            'notice_text' => $data['notice_text'] ?? "Notice is given that the outstanding VATI loan debt must be remedied within fourteen days of {$issuedAt->toDateString()}.",
            'issued_by' => $user->id,
        ]);
        activity()->causedBy($user)->performedOn($loan)->withProperties(['expires_at' => $notice->expires_at])->log('Fourteen-day default notice issued');

        return $notice;
    }

    public function authorizeClearance(Loan $loan, User $user, array $data, UploadedFile $signature): LoanClearance
    {
        if (! $user->hasAnyRole(['super_admin', 'head_office_admin', 'branch_manager'])) {
            throw new DomainException('Only a branch manager or authorized head-office manager may sign a loan clearance.');
        }
        if ($loan->status !== LoanStatus::SETTLED || abs((float) $loan->total_balance) > 0.009) {
            throw new DomainException('Loan clearance requires a fully settled zero-balance loan.');
        }

        $settlement = $loan->settlement;
        $clearance = $loan->clearance()->updateOrCreate(['loan_id' => $loan->id], [
            'loan_outstanding_amount' => 0,
            'security_offset' => $settlement?->security_offset ?? 0,
            'cash_collection' => $settlement?->cash_payment ?? 0,
            'security_refund' => $settlement?->security_refund ?? 0,
            'comments' => $data['comments'] ?? null,
            'status' => 'authorized',
            'authorized_by' => $user->id,
            'authorized_at' => now(),
            'manager_signature_path' => $signature->store('loan-compliance/clearances'),
        ]);
        activity()->causedBy($user)->performedOn($loan)->log('Loan clearance authorized and signed');

        return $clearance;
    }
}
