<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanAssessment extends Model
{
    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
