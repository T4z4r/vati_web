<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGuarantor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['declaration_accepted_at' => 'datetime'];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
