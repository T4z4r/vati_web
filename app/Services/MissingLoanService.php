<?php

namespace App\Services;

use App\Models\LoanApplication;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MissingLoanService
{
    /**
     * Statuses at or past the approval gate. Approving an application opens its
     * loan account, so a gap in these statuses means the account was never
     * issued or was removed behind the application's back.
     */
    public const EXPECTED_LOAN_STATUSES = ['approved', 'disbursement_pending', 'disbursed'];

    public function __construct(private LoanApprovalService $approvals) {}

    /**
     * Applications that reached an approved-or-later status without a linked
     * loan account, newest first. The id breaks ties so applications created
     * within the same second keep a stable order across runs.
     */
    public function find(?int $branchId = null): Collection
    {
        return LoanApplication::with(['member', 'group', 'product'])
            ->whereIn('status', self::EXPECTED_LOAN_STATUSES)
            ->whereDoesntHave('loan')
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Report the applications missing a loan account and, when $issue is on,
     * open the accounts that can be priced. Each application is committed on
     * its own so one unpriceable row does not abandon the rest of the batch.
     *
     * @return array{missing: int, created: int, failed: int, applications: array, issued: array, failures: array, message: string}
     */
    public function run(?int $branchId = null, bool $issue = false): array
    {
        $missing = $this->find($branchId);
        $applications = $missing->map(fn (LoanApplication $application) => [
            'id' => $application->id,
            'application_number' => $application->application_number,
            'member' => trim("{$application->member?->first_name} {$application->member?->last_name}"),
            'group' => $application->group?->group_name,
            'product' => $application->product?->name,
            'status' => $application->status->value,
            'amount' => (float) ($application->recommended_amount ?: $application->requested_amount),
            'duration' => (int) ($application->recommended_duration_months ?: $application->duration_months),
        ])->all();

        $issued = [];
        $failures = [];

        if ($issue) {
            foreach ($missing as $application) {
                try {
                    $loan = DB::transaction(fn () => $this->approvals->issueLoan($application));
                    $issued[] = [
                        'application_number' => $application->application_number,
                        'loan_number' => $loan->loan_number,
                    ];
                } catch (DomainException $e) {
                    $failures[] = [
                        'application_number' => $application->application_number,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'missing' => count($applications),
            'created' => count($issued),
            'failed' => count($failures),
            'applications' => $applications,
            'issued' => $issued,
            'failures' => $failures,
            'message' => $this->message(count($applications), $issue ? count($issued) : null, count($failures)),
        ];
    }

    private function message(int $missing, ?int $created, int $failed): string
    {
        if ($missing === 0) {
            return 'Every approved loan application has a linked loan account — no gaps found.';
        }

        if ($created === null) {
            return sprintf('%d loan application(s) reached an approved status without a loan account.', $missing);
        }

        if ($created === 0 && $failed === 0) {
            return 'No loan accounts were opened.';
        }

        return sprintf(
            'Opened %d of %d missing loan account(s).%s',
            $created,
            $missing,
            $failed > 0 ? sprintf(' %d could not be priced and were left untouched.', $failed) : ''
        );
    }
}
