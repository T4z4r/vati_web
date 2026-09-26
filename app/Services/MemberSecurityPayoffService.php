<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanSettlement;
use App\Models\Member;
use App\Models\MemberSecurityAccount;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberSecurityPayoffService
{
    public function __construct(
        private readonly SecurityAccountService $security,
        private readonly NumberGeneratorService $numbers,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Pay off a member's security: settle the member's eligible loans with the
     * balance first (interest before principal, oldest loan first), then refund
     * whatever is left to the member. Nothing is paid out beyond the recorded
     * balance, and loans are never settled for more than they owe.
     *
     * @return array{message: string, amount: float, offset_total: float, refunded: float, balance_after: float, loans_settled: array<int, array<string, mixed>>, refund: array<string, mixed>|null}
     *
     * @throws DomainException when there is nothing to pay off, the amount is not
     *                       valid, or a refund remains without a payout method.
     */
    public function payOff(Member $member, User $user, array $data): array
    {
        $result = DB::transaction(function () use ($member, $user, $data) {
            $account = MemberSecurityAccount::where('member_id', $member->id)->lockForUpdate()->first();
            if (! $account) {
                throw new DomainException('This member has no security account to pay off.');
            }

            $available = round((float) $account->balance, 2);
            if ($available <= 0) {
                throw new DomainException('This member has no security balance to pay off.');
            }

            $amount = round((float) ($data['amount'] ?? $available), 2);
            if ($amount <= 0) {
                throw new DomainException('The pay-off amount must be greater than zero.');
            }
            if ($amount > $available) {
                throw new DomainException('The pay-off amount cannot exceed the available security balance of TZS '.number_format($available, 2).'.');
            }

            $payoffDate = $this->resolveDate($data['payoff_date'] ?? null);
            $remaining = $amount;
            $loansSettled = [];

            foreach ($this->eligibleLoans($member) as $loan) {
                if ($remaining <= 0.009) {
                    break;
                }

                $outstanding = round((float) $loan->total_balance, 2);
                if ($outstanding <= 0.009) {
                    continue;
                }

                $offset = round(min($remaining, $outstanding), 2);
                $interestOffset = round(min($offset, (float) $loan->interest_balance), 2);
                $principalOffset = round($offset - $interestOffset, 2);

                $settlement = LoanSettlement::create([
                    'settlement_number' => $this->numbers->settlement(),
                    'loan_id' => $loan->id,
                    'settlement_date' => $payoffDate,
                    'principal_outstanding' => $loan->principal_balance,
                    'interest_outstanding' => $loan->interest_balance,
                    'interest_waived' => 0,
                    'security_offset' => $offset,
                    'cash_payment' => 0,
                    'security_refund' => 0,
                    'final_balance' => 0,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                $loan->update([
                    'principal_balance' => round((float) $loan->principal_balance - $principalOffset, 2),
                    'interest_balance' => round((float) $loan->interest_balance - $interestOffset, 2),
                    'total_balance' => 0,
                    'status' => LoanStatus::SETTLED,
                ]);

                $loan->clearance()->updateOrCreate(['loan_id' => $loan->id], [
                    'loan_outstanding_amount' => 0,
                    'security_offset' => $offset,
                    'cash_collection' => 0,
                    'security_refund' => 0,
                    'comments' => 'Security balance applied on pay-off of member security.',
                    'status' => 'pending',
                ]);

                $transaction = $this->security->transact($member, $user, 'loan_offset', $offset, [
                    'loan_id' => $loan->id,
                    'transaction_date' => $payoffDate,
                    'remarks' => 'Security applied to settle loan '.$loan->loan_number,
                ]);

                activity()->causedBy($user)->performedOn($loan)->withProperties([
                    'settlement_number' => $settlement->settlement_number,
                    'security_offset' => $offset,
                    'interest_offset' => $interestOffset,
                    'principal_offset' => $principalOffset,
                ])->log('Loan settled from member security');

                $loansSettled[] = [
                    'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'settlement_number' => $settlement->settlement_number,
                    'outstanding_before' => $outstanding,
                    'interest_offset' => $interestOffset,
                    'principal_offset' => $principalOffset,
                    'offset' => $offset,
                    'transaction_number' => $transaction->transaction_number,
                ];

                $remaining = round($remaining - $offset, 2);
            }

            $refund = null;
            if ($remaining > 0.009) {
                $payoutMethod = $data['payout_method'] ?? null;
                if (! $payoutMethod) {
                    throw new DomainException('A payout method is required to refund the remaining security of TZS '.number_format($remaining, 2).' to the member.');
                }

                $transaction = $this->security->transact($member, $user, 'refund', $remaining, [
                    'transaction_date' => $payoffDate,
                    'payout_method' => $payoutMethod,
                    'payout_reference' => $data['payout_reference'] ?? null,
                    'remarks' => $data['remarks'] ?? 'Remaining security refunded to member on pay-off',
                ]);

                $refund = [
                    'amount' => $remaining,
                    'transaction_number' => $transaction->transaction_number,
                    'payout_method' => $payoutMethod,
                    'payout_reference' => $data['payout_reference'] ?? null,
                ];
            }

            $offsetTotal = round($amount - $remaining, 2);
            $balanceAfter = round((float) $account->refresh()->balance, 2);

            activity()->causedBy($user)->performedOn($member)->withProperties([
                'amount' => $amount,
                'offset_total' => $offsetTotal,
                'refunded' => $remaining,
                'loans_settled' => array_column($loansSettled, 'loan_number'),
            ])->log('Member security paid off');

            return [
                'message' => $this->message($offsetTotal, $loansSettled, $remaining, $balanceAfter),
                'member_id' => $member->id,
                'amount' => $amount,
                'offset_total' => $offsetTotal,
                'refunded' => $remaining,
                'balance_after' => $balanceAfter,
                'loans_settled' => $loansSettled,
                'refund' => $refund,
            ];
        });

        $this->notifyPayoff($member, $result);

        return $result;
    }

    /**
     * Loans that can be cleared from the security balance, oldest first.
     *
     * @return Collection<int, Loan>
     */
    private function eligibleLoans(Member $member): Collection
    {
        return $member->loans()
            ->whereIn('status', [LoanStatus::ACTIVE->value, LoanStatus::OVERDUE->value])
            ->where('total_balance', '>', 0)
            ->whereDoesntHave('settlement')
            ->orderByRaw('maturity_date is null')
            ->orderBy('maturity_date')
            ->orderBy('id')
            ->get();
    }

    private function message(float $offsetTotal, array $loansSettled, float $refunded, float $balanceAfter): string
    {
        $parts = [];
        if ($loansSettled) {
            $parts[] = 'Security of TZS '.number_format($offsetTotal, 2).' settled '.count($loansSettled).' '.Str::plural('loan', count($loansSettled));
        }
        if ($refunded > 0) {
            $parts[] = 'TZS '.number_format($refunded, 2).' refunded to the member';
        }

        return ($parts ? implode('. ', $parts).'.' : 'No security balance required action.').' Security balance is now TZS '.number_format($balanceAfter, 2).'.';
    }

    private function resolveDate(mixed $value): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return $value ? Carbon::parse($value) : now();
    }

    private function notifyPayoff(Member $member, array $result): void
    {
        $recipients = User::query()
            ->where(fn ($query) => $query->where('branch_id', $member->branch_id)->orWhereNull('branch_id'))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['branch_manager', 'head_office_admin', 'super_admin']))
            ->get()
            ->merge(collect([$member->group?->loanOfficer])->filter());

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $recipients,
            'member_security_paid_off',
            'Member security paid off',
            $result['message'],
            'member',
            $member->id
        );
    }
}
