<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LoanInstallmentRecord extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'actual_payment_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'interest_exemption' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'is_paid' => 'boolean',
        ];
    }

    public function activityDescription(): string
    {
        return 'Installment Record';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['installment_number', 'principal_amount', 'interest_amount', 'is_paid'])
            ->useLogName('installment_record');
    }

    public function loanCycle()
    {
        return $this->belongsTo(LoanCycle::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function getStatusBadgeAttribute()
    {
        if ($this->is_paid) {
            return 'paid';
        }
        return now()->isAfter($this->payment_date) ? 'overdue' : 'pending';
    }

    public function getFormattedPrincipalAttribute()
    {
        return number_format($this->principal_amount ?? 0, 2);
    }

    public function getFormattedInterestAttribute()
    {
        return number_format($this->interest_amount ?? 0, 2);
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount ?? 0, 2);
    }

    public function getFormattedBalanceAttribute()
    {
        return number_format($this->outstanding_balance ?? 0, 2);
    }
}
