<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanSettlement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settlement_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
