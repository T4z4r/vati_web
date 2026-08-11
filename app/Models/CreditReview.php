<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recommended_amount' => 'decimal:2',
            'member_verified' => 'boolean',
            'group_membership_verified' => 'boolean',
            'documents_verified' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
