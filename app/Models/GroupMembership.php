<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMembership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['joined_at' => 'date', 'left_at' => 'date'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class);
    }
}
