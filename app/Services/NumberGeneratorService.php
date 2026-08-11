<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public function member(): string
    {
        return $this->next('members', 'members', 'VATI-M');
    }

    public function region(): string
    {
        return $this->next('regions', 'regions', 'VATI-REG');
    }

    public function area(): string
    {
        return $this->next('areas', 'areas', 'VATI-AREA');
    }

    public function branch(): string
    {
        return $this->next('branches', 'branches', 'VATI-BR');
    }

    public function group(): string
    {
        return $this->next('member_groups', 'member_groups', 'VATI-GRP');
    }

    public function loanProduct(): string
    {
        return $this->next('loan_products', 'loan_products', 'VATI-LP');
    }

    public function application(): string
    {
        return $this->next('loan_applications', 'loan_applications', 'VATI-LAF');
    }

    public function loan(): string
    {
        return $this->next('loans', 'loans', 'VATI-LN');
    }

    public function payment(): string
    {
        return $this->next('payments', 'payments', 'VATI-PAY');
    }

    public function settlement(): string
    {
        return $this->next('loan_settlements', 'loan_settlements', 'VATI-STL');
    }

    public function security(): string
    {
        return $this->next('security_transactions', 'security_transactions', 'VATI-SEC');
    }

    private function next(string $sequence, string $table, string $prefix): string
    {
        return DB::transaction(function () use ($sequence, $table, $prefix) {
            DB::table('number_sequences')->insertOrIgnore([
                'name' => $sequence,
                'next_value' => ((int) DB::table($table)->max('id')) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('number_sequences')->where('name', $sequence)->lockForUpdate()->first();
            $next = (int) $row->next_value;
            DB::table('number_sequences')->where('name', $sequence)->update(['next_value' => $next + 1, 'updated_at' => now()]);

            return sprintf('%s-%s-%06d', $prefix, now()->format('Y'), $next);
        });
    }
}
