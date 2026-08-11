<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupCollection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['collection_date' => 'date'];
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    public function meeting()
    {
        return $this->belongsTo(GroupMeeting::class, 'group_meeting_id');
    }
}
