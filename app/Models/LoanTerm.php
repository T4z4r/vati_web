<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean'];
    }
}
