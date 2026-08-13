<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberFamilyMember extends Model
{
    protected $guarded = [];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
