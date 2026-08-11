<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public function member(): string
    {
        return $this->next('members', 'VATI-M');
    }

    public function application(): string
    {
        return $this->next('loan_applications', 'VATI-LAF');
    }

    public function loan(): string
    {
        return $this->next('loans', 'VATI-LN');
    }

    public function payment(): string
    {
        return $this->next('payments', 'VATI-PAY');
    }

    public function settlement(): string
    {
        return $this->next('loan_settlements', 'VATI-STL');
    }

    public function security(): string
    {
        return $this->next('security_transactions', 'VATI-SEC');
    }

    private function next(string $table, string $prefix): string
    {
        $next = ((int) DB::table($table)->lockForUpdate()->max('id')) + 1;

        return sprintf('%s-%s-%06d', $prefix, now()->format('Y'), $next);
    }
}
