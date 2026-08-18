<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DataPurgeService
{
    public function summary(): array
    {
        return [
            'members' => [
                'label' => 'Members',
                'count' => Member::count(),
                'deletable' => Member::count(),
                'description' => 'Registered members and all associated data.',
            ],
            'groups' => [
                'label' => 'Groups',
                'count' => MemberGroup::count(),
                'deletable' => MemberGroup::count(),
                'description' => 'Member groups, meetings, collections, and visits.',
            ],
            'applications' => [
                'label' => 'Loan Applications',
                'count' => LoanApplication::count(),
                'deletable' => LoanApplication::count(),
                'description' => 'Loan applications, assessments, guarantors, documents, and approvals.',
            ],
            'loans' => [
                'label' => 'Loans',
                'count' => Loan::count(),
                'deletable' => Loan::count(),
                'description' => 'Active and closed loans, installments, payments, and schedules.',
            ],
            'loan_products' => [
                'label' => 'Loan Products',
                'count' => LoanProduct::count(),
                'deletable' => LoanProduct::count(),
                'description' => 'Loan product configurations and pricing rules.',
            ],
        ];
    }

    public function preview(string $entity, ?string $from = null, ?string $to = null, ?int $branchId = null): array
    {
        $query = match ($entity) {
            'members' => Member::query(),
            'groups' => MemberGroup::query(),
            'applications' => LoanApplication::query(),
            'loans' => Loan::query(),
            'loan_products' => LoanProduct::query(),
            default => throw new \DomainException('Unknown entity type.'),
        };

        if ($from && $to) {
            $dateColumn = match ($entity) {
                'members' => 'admission_date',
                'groups' => 'created_at',
                'applications' => 'created_at',
                'loans' => 'disbursement_date',
                'loan_products' => 'created_at',
                default => 'created_at',
            };
            $query->whereBetween($dateColumn, [$from, $to]);
        }

        if ($branchId && in_array($entity, ['members', 'groups', 'applications', 'loans'])) {
            $query->where('branch_id', $branchId);
        }

        $count = $query->count();
        $sample = $query->limit(10)->get();

        $cascade = $this->estimateCascade($entity, $query);

        return [
            'entity' => $entity,
            'count' => $count,
            'sample' => $sample,
            'cascade' => $cascade,
        ];
    }

    public function validate(string $entity, ?string $from = null, ?string $to = null, ?int $branchId = null): array
    {
        $errors = [];
        $warnings = [];

        $query = $this->buildQuery($entity, $from, $to, $branchId);

        match ($entity) {
            'members' => $this->validateMembers($query, $errors, $warnings),
            'groups' => $this->validateGroups($query, $errors, $warnings),
            'applications' => $this->validateApplications($query, $errors, $warnings),
            'loans' => $this->validateLoans($query, $errors, $warnings),
            'loan_products' => $this->validateLoanProducts($query, $errors, $warnings),
            default => $errors[] = 'Unknown entity type.',
        };

        return ['valid' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    public function purge(string $entity, ?string $from = null, ?string $to = null, ?int $branchId = null, ?string $confirmationPhrase = null): array
    {
        if ($confirmationPhrase !== 'DELETE ALL DATA') {
            throw new \DomainException('Confirmation phrase mismatch. Type "DELETE ALL DATA" to proceed.');
        }

        $query = $this->buildQuery($entity, $from, $to, $branchId);
        $count = $query->count();

        if ($count === 0) {
            return ['deleted' => 0, 'message' => 'No records matched the criteria.'];
        }

        $deleted = DB::transaction(function () use ($entity, $query, $count) {
            $records = $query->pluck('id');
            $deletedCounts = [];

            match ($entity) {
                'members' => $deletedCounts = $this->purgeMembers($records),
                'groups' => $deletedCounts = $this->purgeGroups($records),
                'applications' => $deletedCounts = $this->purgeApplications($records),
                'loans' => $deletedCounts = $this->purgeLoans($records),
                'loan_products' => $deletedCounts = $this->purgeLoanProducts($records),
            };

            return ['main' => $count, 'cascade' => $deletedCounts];
        });

        return [
            'deleted' => $deleted['main'],
            'cascade' => $deleted['cascade'],
            'message' => "Successfully purged {$deleted['main']} {$entity} records.",
        ];
    }

    private function buildQuery(string $entity, ?string $from, ?string $to, ?int $branchId)
    {
        $query = match ($entity) {
            'members' => Member::query(),
            'groups' => MemberGroup::query(),
            'applications' => LoanApplication::query(),
            'loans' => Loan::query(),
            'loan_products' => LoanProduct::query(),
        };

        if ($from && $to) {
            $dateColumn = match ($entity) {
                'members' => 'admission_date',
                default => 'created_at',
            };
            $query->whereBetween($dateColumn, [$from, $to]);
        }

        if ($branchId && in_array($entity, ['members', 'groups', 'applications', 'loans'])) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    private function validateMembers($query, array &$errors, array &$warnings): void
    {
        $ids = $query->pluck('id');

        $activeLoans = Loan::whereIn('member_id', $ids)
            ->whereIn('status', ['pending_disbursement', 'active', 'overdue'])
            ->count();

        if ($activeLoans > 0) {
            $errors[] = "{$activeLoans} member(s) have active loans and cannot be purged.";
        }

        $openApps = LoanApplication::whereIn('member_id', $ids)
            ->whereNotIn('status', ['rejected', 'cancelled', 'disbursed'])
            ->count();

        if ($openApps > 0) {
            $warnings[] = "{$openApps} member(s) have open applications that will be cascade-deleted.";
        }
    }

    private function validateGroups($query, array &$errors, array &$warnings): void
    {
        $ids = $query->pluck('id');

        $activeLoans = Loan::whereIn('group_id', $ids)
            ->whereIn('status', ['pending_disbursement', 'active', 'overdue'])
            ->count();

        if ($activeLoans > 0) {
            $errors[] = "{$activeLoans} group(s) have active loans and cannot be purged.";
        }

        $openApps = LoanApplication::whereIn('group_id', $ids)
            ->whereNotIn('status', ['rejected', 'cancelled', 'disbursed'])
            ->count();

        if ($openApps > 0) {
            $warnings[] = "{$openApps} group(s) have open applications that will be cascade-deleted.";
        }
    }

    private function validateApplications($query, array &$errors, array &$warnings): void
    {
        $ids = $query->pluck('id');

        $withLoans = Loan::whereIn('loan_application_id', $ids)->count();

        if ($withLoans > 0) {
            $warnings[] = "{$withLoans} application(s) have associated loans. Purge the loans first, or they will be orphaned.";
        }

        $approved = LoanApplication::whereIn('id', $ids)
            ->whereIn('status', ['approved', 'disbursement_pending', 'disbursed'])
            ->count();

        if ($approved > 0) {
            $warnings[] = "{$approved} application(s) are approved/disbursed. Associated loan records may be affected.";
        }
    }

    private function validateLoans($query, array &$errors, array &$warnings): void
    {
        $ids = $query->pluck('id');

        $withPayments = Payment::whereIn('loan_id', $ids)
            ->where('status', 'posted')
            ->count();

        if ($withPayments > 0) {
            $errors[] = "{$withPayments} loan(s) have posted payments and cannot be purged. Reverse payments first.";
        }

        $settledWithClearance = Loan::whereIn('id', $ids)
            ->where('status', 'settled')
            ->whereHas('clearance', fn ($q) => $q->where('status', 'authorized'))
            ->count();

        if ($settledWithClearance > 0) {
            $warnings[] = "{$settledWithClearance} loan(s) are settled with authorized clearance. These will still be purged.";
        }
    }

    private function validateLoanProducts($query, array &$errors, array &$warnings): void
    {
        $ids = $query->pluck('id');

        $withApps = LoanApplication::whereIn('loan_product_id', $ids)->count();
        $withLoans = Loan::whereIn('loan_product_id', $ids)->count();

        if ($withApps > 0 || $withLoans > 0) {
            $errors[] = "{$withApps} application(s) and {$withLoans} loan(s) use these products. Cannot purge.";
        }
    }

    private function estimateCascade(string $entity, $query): array
    {
        $ids = $query->pluck('id');
        $counts = [];

        match ($entity) {
            'members' => $counts = [
                'Member KYC' => DB::table('member_kycs')->whereIn('member_id', $ids)->count(),
                'Nominees' => DB::table('member_nominees')->whereIn('member_id', $ids)->count(),
                'Family Members' => DB::table('member_family_members')->whereIn('member_id', $ids)->count(),
                'Assets' => DB::table('member_assets')->whereIn('member_id', $ids)->count(),
                'Documents' => DB::table('member_documents')->whereIn('member_id', $ids)->count(),
                'Group Memberships' => DB::table('group_memberships')->whereIn('member_id', $ids)->count(),
                'Security Accounts' => DB::table('member_security_accounts')->whereIn('member_id', $ids)->count(),
            ],
            'groups' => $counts = [
                'Members' => Member::whereIn('group_id', $ids)->count(),
                'Memberships' => DB::table('group_memberships')->whereIn('group_id', $ids)->count(),
                'Meetings' => DB::table('group_meetings')->whereIn('group_id', $ids)->count(),
                'Collections' => DB::table('group_collections')->whereIn('group_id', $ids)->count(),
                'Visits' => DB::table('group_visits')->whereIn('group_id', $ids)->count(),
            ],
            'applications' => $counts = [
                'Assessments' => DB::table('loan_assessments')->whereIn('loan_application_id', $ids)->count(),
                'Utilizations' => DB::table('loan_utilizations')->whereIn('loan_application_id', $ids)->count(),
                'Guarantors' => DB::table('loan_guarantors')->whereIn('loan_application_id', $ids)->count(),
                'Documents' => DB::table('loan_documents')->whereIn('loan_application_id', $ids)->count(),
                'Witnesses' => DB::table('loan_group_witnesses')->whereIn('loan_application_id', $ids)->count(),
                'Approvals' => DB::table('loan_approvals')->whereIn('loan_application_id', $ids)->count(),
                'Credit Reviews' => DB::table('credit_reviews')->whereIn('loan_application_id', $ids)->count(),
                'Cancellations' => DB::table('loan_cancellations')->whereIn('loan_application_id', $ids)->count(),
            ],
            'loans' => $counts = [
                'Installments' => DB::table('loan_installments')->whereIn('loan_id', $ids)->count(),
                'Payments' => DB::table('payments')->whereIn('loan_id', $ids)->count(),
                'Disbursements' => DB::table('loan_disbursements')->whereIn('loan_id', $ids)->count(),
                'Settlements' => DB::table('loan_settlements')->whereIn('loan_id', $ids)->count(),
                'Clearances' => DB::table('loan_clearances')->whereIn('loan_id', $ids)->count(),
                'Default Notices' => DB::table('loan_default_notices')->whereIn('loan_id', $ids)->count(),
                'Cycles' => DB::table('loan_cycles')->whereIn('loan_id', $ids)->count(),
                'Security Transactions' => DB::table('loan_security_transactions')->whereIn('loan_id', $ids)->count(),
                'Refinancings (old)' => DB::table('loan_refinancings')->whereIn('old_loan_id', $ids)->count(),
                'Refinancings (new)' => DB::table('loan_refinancings')->whereIn('new_loan_id', $ids)->count(),
            ],
            'loan_products' => [],
        };

        return $counts;
    }

    private function purgeMembers($records): array
    {
        $c = [];
        $c['member_kycs'] = DB::table('member_kycs')->whereIn('member_id', $records)->delete();
        $c['member_nominees'] = DB::table('member_nominees')->whereIn('member_id', $records)->delete();
        $c['member_family_members'] = DB::table('member_family_members')->whereIn('member_id', $records)->delete();
        $c['member_assets'] = DB::table('member_assets')->whereIn('member_id', $records)->delete();
        $c['member_documents'] = DB::table('member_documents')->whereIn('member_id', $records)->delete();
        $c['group_memberships'] = DB::table('group_memberships')->whereIn('member_id', $records)->delete();
        $c['passbook_replacements'] = DB::table('passbook_replacements')->whereIn('member_id', $records)->delete();
        $c['security_transactions'] = DB::table('security_transactions')
            ->whereIn('member_security_account_id', function ($q) use ($records) {
                $q->select('id')->from('member_security_accounts')->whereIn('member_id', $records);
            })->delete();
        $c['member_security_accounts'] = DB::table('member_security_accounts')->whereIn('member_id', $records)->delete();
        $c['members'] = Member::whereIn('id', $records)->delete();

        return $c;
    }

    private function purgeGroups($records): array
    {
        $c = [];
        $c['group_memberships'] = DB::table('group_memberships')->whereIn('group_id', $records)->delete();
        $c['group_meetings'] = DB::table('group_meetings')->whereIn('group_id', $records)->delete();
        $c['group_collections'] = DB::table('group_collections')->whereIn('group_id', $records)->delete();
        $c['group_visits'] = DB::table('group_visits')->whereIn('group_id', $records)->delete();
        $c['member_groups'] = MemberGroup::whereIn('id', $records)->delete();

        return $c;
    }

    private function purgeApplications($records): array
    {
        $c = [];
        $c['loan_assessments'] = DB::table('loan_assessments')->whereIn('loan_application_id', $records)->delete();
        $c['loan_utilizations'] = DB::table('loan_utilizations')->whereIn('loan_application_id', $records)->delete();
        $c['loan_guarantors'] = DB::table('loan_guarantors')->whereIn('loan_application_id', $records)->delete();
        $c['loan_documents'] = DB::table('loan_documents')->whereIn('loan_application_id', $records)->delete();
        $c['loan_group_witnesses'] = DB::table('loan_group_witnesses')->whereIn('loan_application_id', $records)->delete();
        $c['loan_approvals'] = DB::table('loan_approvals')->whereIn('loan_application_id', $records)->delete();
        $c['credit_reviews'] = DB::table('credit_reviews')->whereIn('loan_application_id', $records)->delete();
        $c['loan_cancellations'] = DB::table('loan_cancellations')->whereIn('loan_application_id', $records)->delete();
        $c['loan_applications'] = LoanApplication::whereIn('id', $records)->delete();

        return $c;
    }

    private function purgeLoans($records): array
    {
        $c = [];
        $c['payment_allocations'] = DB::table('payment_allocations')
            ->whereIn('payment_id', function ($q) use ($records) {
                $q->select('id')->from('payments')->whereIn('loan_id', $records);
            })->delete();
        $c['payments'] = DB::table('payments')->whereIn('loan_id', $records)->delete();
        $c['loan_installments'] = DB::table('loan_installments')->whereIn('loan_id', $records)->delete();
        $c['loan_disbursements'] = DB::table('loan_disbursements')->whereIn('loan_id', $records)->delete();
        $c['loan_settlements'] = DB::table('loan_settlements')->whereIn('loan_id', $records)->delete();
        $c['loan_clearances'] = DB::table('loan_clearances')->whereIn('loan_id', $records)->delete();
        $c['loan_default_notices'] = DB::table('loan_default_notices')->whereIn('loan_id', $records)->delete();
        $c['loan_cycles'] = DB::table('loan_cycles')->whereIn('loan_id', $records)->delete();
        $c['loan_installment_records'] = DB::table('loan_installment_records')->whereIn('loan_id', $records)->delete();
        $c['loan_security_transactions'] = DB::table('loan_security_transactions')->whereIn('loan_id', $records)->delete();
        $c['loan_refinancings'] = DB::table('loan_refinancings')
            ->whereIn('old_loan_id', $records)
            ->orWhereIn('new_loan_id', $records)
            ->delete();
        $c['loans'] = Loan::whereIn('id', $records)->delete();

        return $c;
    }

    private function purgeLoanProducts($records): array
    {
        $c = [];
        $c['loan_products'] = LoanProduct::whereIn('id', $records)->delete();

        return $c;
    }
}
