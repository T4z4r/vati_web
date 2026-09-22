<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Member;
use App\Models\MemberDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MemberDeletionService
{
    /**
     * Permanently delete a member together with every record linked to them
     * (loans, loan applications, payments, security accounts, documents,
     * memberships, witnesses, etc.) within a single transaction.
     *
     * @return array<string, int> Map of table => deleted row count.
     */
    public function forceDelete(Member $member): array
    {
        return DB::transaction(function () use ($member) {
            $memberId = (int) $member->id;
            $counts = [];

            // Payments reference the member (restrict) and their loans (restrict);
            // payment_allocations cascade automatically when a payment is removed.
            $counts['payments'] = DB::table('payments')->where('member_id', $memberId)->delete();

            $loanIds = DB::table('loans')->where('member_id', $memberId)->pluck('id');

            if ($loanIds->isNotEmpty()) {
                // Restrict-on-delete children of loans must go before the loan row.
                $counts['loan_disbursements'] = DB::table('loan_disbursements')->whereIn('loan_id', $loanIds)->delete();
                $counts['loan_settlements'] = DB::table('loan_settlements')->whereIn('loan_id', $loanIds)->delete();
                $counts['loan_clearances'] = DB::table('loan_clearances')->whereIn('loan_id', $loanIds)->delete();
                $counts['loan_default_notices'] = DB::table('loan_default_notices')->whereIn('loan_id', $loanIds)->delete();
                $counts['loan_refinancings'] = DB::table('loan_refinancings')
                    ->whereIn('old_loan_id', $loanIds)
                    ->orWhereIn('new_loan_id', $loanIds)
                    ->delete();
                DB::table('security_transactions')->whereIn('loan_id', $loanIds)->delete();
                // Loans cascade installments, cycles, installment records and loan
                // security transactions at the database level.
                $counts['loans'] = DB::table('loans')->whereIn('id', $loanIds)->delete();
            }

            // Loan applications (soft-deletable) plus their restrict child.
            $applicationIds = LoanApplication::where('member_id', $memberId)->withTrashed()->pluck('id');
            if ($applicationIds->isNotEmpty()) {
                DB::table('loan_cancellations')->whereIn('loan_application_id', $applicationIds)->delete();
                $this->deleteApplicationFiles($applicationIds);
                $counts['loan_applications'] = LoanApplication::whereIn('id', $applicationIds)->withTrashed()->forceDelete();
            }

            // The member may have signed as a witness on other members' applications.
            $counts['loan_group_witnesses'] = DB::table('loan_group_witnesses')->where('member_id', $memberId)->delete();

            // Detach signature documents before removing the member's documents.
            $documentIds = MemberDocument::where('member_id', $memberId)->withTrashed()->pluck('id');
            if ($documentIds->isNotEmpty()) {
                DB::table('loan_group_witnesses')->whereIn('signature_document_id', $documentIds)->update(['signature_document_id' => null]);
            }
            DB::table('member_documents')->where('active_signature_member_id', $memberId)->update(['active_signature_member_id' => null]);

            // Security account transactions restrict on the account, the account cascades.
            $accountIds = DB::table('member_security_accounts')->where('member_id', $memberId)->pluck('id');
            if ($accountIds->isNotEmpty()) {
                $counts['security_transactions'] = DB::table('security_transactions')->whereIn('member_security_account_id', $accountIds)->delete();
                $counts['member_security_accounts'] = DB::table('member_security_accounts')->whereIn('id', $accountIds)->delete();
            }

            $counts['group_memberships'] = DB::table('group_memberships')->where('member_id', $memberId)->delete();
            $counts['passbook_replacements'] = DB::table('passbook_replacements')->where('member_id', $memberId)->delete();
            $counts['group_attendances'] = DB::table('group_attendances')->where('member_id', $memberId)->delete();
            $counts['payment_transactions'] = DB::table('payment_transactions')->where('member_id', $memberId)->delete();

            $counts['member_kycs'] = DB::table('member_kycs')->where('member_id', $memberId)->delete();
            $counts['member_nominees'] = DB::table('member_nominees')->where('member_id', $memberId)->delete();
            $counts['member_family_members'] = DB::table('member_family_members')->where('member_id', $memberId)->delete();
            $counts['member_assets'] = DB::table('member_assets')->where('member_id', $memberId)->delete();

            // Polymorphic links with no FK constraint: notifications and activity log.
            $counts['notifications'] = DB::table('notifications')
                ->where('notifiable_type', Member::class)
                ->where('notifiable_id', $memberId)
                ->delete();
            $counts['activity_log'] = DB::table('activity_log')
                ->where(function ($q) use ($memberId) {
                    $q->where(fn ($q) => $q->where('subject_type', Member::class)->where('subject_id', $memberId))
                        ->orWhere(fn ($q) => $q->where('causer_type', Member::class)->where('causer_id', $memberId));
                })
                ->delete();

            $counts['member_documents'] = $this->deleteMemberDocuments($member);

            $this->deleteStoredFile($member->photo_path);

            $member->forceDelete();

            return $counts;
        });
    }

    private function deleteMemberDocuments(Member $member): int
    {
        $deleted = 0;
        MemberDocument::where('member_id', $member->id)->withTrashed()->each(function (MemberDocument $document) use (&$deleted) {
            $this->deleteStoredFile($document->file_path, $document->disk);
            $document->forceDelete();
            $deleted++;
        });

        return $deleted;
    }

    private function deleteApplicationFiles($applicationIds): void
    {
        foreach (DB::table('loan_documents')->whereIn('loan_application_id', $applicationIds)->pluck('file_path') as $filePath) {
            $this->deleteStoredFile($filePath);
        }

        $signaturePaths = DB::table('loan_applications')
            ->whereIn('id', $applicationIds)
            ->get(['applicant_signature_path', 'applicant_thumbprint_path'])
            ->flatMap(fn ($row) => [$row->applicant_signature_path, $row->applicant_thumbprint_path]);

        foreach ($signaturePaths as $filePath) {
            $this->deleteStoredFile($filePath);
        }

        $guarantorPaths = DB::table('loan_guarantors')
            ->whereIn('loan_application_id', $applicationIds)
            ->get(['signature_path', 'thumbprint_path', 'joint_photo_path'])
            ->flatMap(fn ($row) => [$row->signature_path, $row->thumbprint_path, $row->joint_photo_path]);

        foreach ($guarantorPaths as $filePath) {
            $this->deleteStoredFile($filePath);
        }
    }

    private function deleteStoredFile(?string $filePath, ?string $preferredDisk = null): void
    {
        if (! $filePath) {
            return;
        }

        $disks = array_values(array_unique(array_filter([
            $preferredDisk,
            config('filesystems.default'),
            'public',
        ])));

        foreach ($disks as $disk) {
            try {
                $storage = Storage::disk($disk);
                if ($storage->exists($filePath)) {
                    $storage->delete($filePath);
                    return;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}