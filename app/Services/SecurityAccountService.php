<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberSecurityAccount;
use App\Models\SecurityTransaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class SecurityAccountService
{
    public function __construct(private NumberGeneratorService $numbers) {}

    public function transact(Member $member, User $user, string $type, float $amount, array $data = []): SecurityTransaction
    {
        if ($amount <= 0 || ! in_array($type, ['deposit', 'withdrawal', 'loan_offset', 'refund', 'adjustment'], true)) {
            throw new DomainException('Invalid security transaction.');
        }

        return DB::transaction(function () use ($member, $user, $type, $amount, $data) {
            MemberSecurityAccount::firstOrCreate(['member_id' => $member->id]);
            $account = MemberSecurityAccount::where('member_id', $member->id)->lockForUpdate()->firstOrFail();
            $before = (float) $account->balance;
            $credit = in_array($type, ['deposit', 'adjustment'], true);
            $after = round($before + ($credit ? $amount : -$amount), 2);
            if ($after < 0) {
                throw new DomainException('Insufficient security balance.');
            }
            $account->update(['balance' => $after]);

            return $account->transactions()->create([
                'transaction_number' => $this->numbers->security(),
                'loan_id' => $data['loan_id'] ?? null,
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $user->id,
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);
        });
    }
}
