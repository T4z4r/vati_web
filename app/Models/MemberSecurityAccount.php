<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberSecurityAccount extends Model
{
    protected $guarded = [];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function transactions()
    {
        return $this->hasMany(SecurityTransaction::class);
    }
}
