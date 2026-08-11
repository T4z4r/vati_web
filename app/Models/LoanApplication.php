<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'consented_at' => 'datetime',
            'cancellation_deadline' => 'datetime',
            'recommended_amount' => 'decimal:2',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assessment()
    {
        return $this->hasOne(LoanAssessment::class);
    }

    public function approvals()
    {
        return $this->hasMany(LoanApproval::class);
    }

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    public function groupWitnesses()
    {
        return $this->hasMany(LoanGroupWitness::class);
    }

    public function term()
    {
        return $this->belongsTo(LoanTerm::class, 'loan_term_id');
    }

    public function guarantors()
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function documents()
    {
        return $this->hasMany(LoanDocument::class);
    }

    public function cancellation()
    {
        return $this->hasOne(LoanCancellation::class);
    }

    public function utilizations()
    {
        return $this->hasMany(LoanUtilization::class);
    }

    public function creditReviews()
    {
        return $this->hasMany(CreditReview::class);
    }

    public function latestCreditReview()
    {
        return $this->hasOne(CreditReview::class)->latestOfMany();
    }

    public function assignedCreditOfficer()
    {
        return $this->belongsTo(User::class, 'assigned_credit_officer_id');
    }
}
