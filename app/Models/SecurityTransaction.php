<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['transaction_date' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(MemberSecurityAccount::class, 'member_security_account_id');
    }
}
