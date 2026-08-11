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
            'status' => LoanStatus::class, 'disbursement_date' => 'date',
            'first_payment_date' => 'date', 'maturity_date' => 'date',
            'principal_amount' => 'decimal:2', 'interest_amount' => 'decimal:2',
            'total_repayment' => 'decimal:2', 'principal_balance' => 'decimal:2',
            'interest_balance' => 'decimal:2', 'total_balance' => 'decimal:2',
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

    public function group() { return $this->belongsTo(MemberGroup::class); }

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
}
