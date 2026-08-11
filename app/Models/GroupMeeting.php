<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMeeting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meeting_date' => 'date'];
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }
}
