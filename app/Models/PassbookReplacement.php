<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassbookReplacement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fee_amount' => 'decimal:2', 'paid_at' => 'datetime', 'issued_at' => 'datetime'];
    }
}
