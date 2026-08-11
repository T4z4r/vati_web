<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupAttendance extends Model
{
    protected $guarded = [];

    public function meeting()
    {
        return $this->belongsTo(GroupMeeting::class, 'group_meeting_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
