<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberNominee extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2', 'attested_at' => 'datetime'];
    }
}
