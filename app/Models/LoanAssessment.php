<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanAssessment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'core_business_income' => 'decimal:2',
            'other_income' => 'decimal:2',
            'business_expenses' => 'decimal:2',
            'household_expenses' => 'decimal:2',
            'existing_external_debt' => 'decimal:2',
            'external_loan_total_amount' => 'decimal:2',
            'external_loan_outstanding_amount' => 'decimal:2',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
