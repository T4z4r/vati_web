<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2', 'maximum_amount' => 'decimal:2',
            'annual_interest_rate' => 'decimal:4', 'security_percentage' => 'decimal:4',
            'processing_fee_percentage' => 'decimal:4', 'transaction_fee_percentage' => 'decimal:4',
            'membership_fee' => 'decimal:2', 'vat_percentage' => 'decimal:4', 'status' => 'boolean',
            'required_group_witnesses' => 'integer',
        ];
    }
}
