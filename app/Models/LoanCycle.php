<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanCycle extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'first_payment_date' => 'date',
            'is_main_cycle' => 'boolean',
            'is_refinancing_cycle' => 'boolean',
            'principal_amount' => 'decimal:2',
            'adjusted_principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'admission_fee' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'transaction_charges' => 'decimal:2',
            'increment_amount' => 'decimal:2',
            'refinancing_amount' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'total_fees_and_vat' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_with_interest' => 'decimal:2',
            'weekly_installment' => 'decimal:2',
            'total_installments' => 'integer',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function installmentRecords()
    {
        return $this->hasMany(LoanInstallmentRecord::class);
    }

    public function getProjectNameAttribute()
    {
        return $this->business_name ?? ($this->loan?->application?->project_name ?? 'N/A');
    }

    public function getTotalAmountDueAttribute()
    {
        return ($this->principal_amount ?? 0) + ($this->interest_rate ?? 0);
    }

    public function getFormattedInterestRateAttribute()
    {
        return number_format($this->interest_rate ?? 0, 2) . '%';
    }
}
