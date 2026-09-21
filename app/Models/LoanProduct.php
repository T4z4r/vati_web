<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'processing_fee_percentage' => 1,
        'insurance_percentage' => 1.5,
        'vat_percentage' => 18,
        'security_percentage' => 10,
        'transaction_fee_percentage' => 0,
        'membership_fee' => 0,
        'annual_interest_rate' => 0,
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2', 'maximum_amount' => 'decimal:2',
            'annual_interest_rate' => 'decimal:4', 'security_percentage' => 'decimal:4',
            'processing_fee_percentage' => 'decimal:4', 'transaction_fee_percentage' => 'decimal:4',
            'insurance_percentage' => 'decimal:4',
            'membership_fee' => 'decimal:2', 'vat_percentage' => 'decimal:4', 'status' => 'boolean',
            'required_group_witnesses' => 'integer',
        ];
    }

    public function applications()
    {
        return $this->hasMany(LoanApplication::class);
    }
}
