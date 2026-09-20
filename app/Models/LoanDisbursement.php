<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDisbursement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['disbursed_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
