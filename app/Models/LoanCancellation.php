<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCancellation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }
}
