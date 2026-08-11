<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanClearance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['authorized_at' => 'datetime'];
    }
}
