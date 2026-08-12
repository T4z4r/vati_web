<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LoanSecurityTransaction extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    protected $appends = ['formatted_amount', 'formatted_balance'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'security_amount' => 'decimal:2',
            'withdrawal_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function activityDescription(): string
    {
        return 'Security Transaction';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['loan_id', 'security_amount', 'withdrawal_amount', 'balance'])
            ->useLogName('security_transaction');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeByLoan($query, $loanId)
    {
        return $query->where('loan_id', $loanId)->orderBy('transaction_date', 'desc');
    }

    public function scopeDeposits($query)
    {
        return $query->whereColumn('security_amount', '>', 0);
    }

    public function scopeWithdrawals($query)
    {
        return $query->whereColumn('withdrawal_amount', '>', 0);
    }

    public function getFormattedAmountAttribute()
    {
        $deposit = $this->security_amount ?? 0;
        $withdrawal = $this->withdrawal_amount ?? 0;
        $amount = $deposit > 0 ? $deposit : $withdrawal;
        return number_format($amount, 2);
    }

    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance ?? 0, 2);
    }

    public function getTransactionTypeAttribute()
    {
        if (($this->security_amount ?? 0) > 0) {
            return 'deposit';
        } elseif (($this->withdrawal_amount ?? 0) > 0) {
            return 'withdrawal';
        }
        return 'unknown';
    }
}
