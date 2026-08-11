<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDefaultNotice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }
}
