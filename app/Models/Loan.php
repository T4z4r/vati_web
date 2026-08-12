<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'disbursement_date' => 'date',
            'first_payment_date' => 'date',
            'maturity_date' => 'date',
            'principal_amount' => 'decimal:2',
            'adjusted_principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_repayment' => 'decimal:2',
            'principal_balance' => 'decimal:2',
            'interest_balance' => 'decimal:2',
            'total_balance' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'admission_fee' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'transaction_charges' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'total_fees_and_vat' => 'decimal:2',
            'weekly_installment' => 'decimal:2',
            'refinancing_amount' => 'decimal:2',
            'increment_amount' => 'decimal:2',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function installments()
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function disbursement()
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    public function defaultNotices()
    {
        return $this->hasMany(LoanDefaultNotice::class);
    }

    public function clearance()
    {
        return $this->hasOne(LoanClearance::class);
    }

    public function settlement()
    {
        return $this->hasOne(LoanSettlement::class);
    }

    public function cycles()
    {
        return $this->hasMany(LoanCycle::class);
    }

    public function currentCycle()
    {
        return $this->hasOne(LoanCycle::class)->where('status', 'active')->latest();
    }

    public function mainCycle()
    {
        return $this->hasOne(LoanCycle::class)->where('is_main_cycle', true);
    }

    public function refinancingCycle()
    {
        return $this->hasOne(LoanCycle::class)->where('is_refinancing_cycle', true);
    }

    public function installmentRecords()
    {
        return $this->hasMany(LoanInstallmentRecord::class);
    }

    public function securityTransactions()
    {
        return $this->hasMany(LoanSecurityTransaction::class);
    }

    public function getTotalSecurityAmountAttribute()
    {
        return $this->securityTransactions()
            ->where('security_amount', '>', 0)
            ->sum('security_amount');
    }

    public function getCurrentSecurityBalanceAttribute()
    {
        return $this->securityTransactions()
            ->latest('transaction_date')
            ->value('balance') ?? 0;
    }
}
