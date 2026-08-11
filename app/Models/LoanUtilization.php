<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanUtilization extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['allocation_amount' => 'decimal:2', 'current_asset_value' => 'decimal:2'];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
