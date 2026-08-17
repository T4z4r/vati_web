<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupVisit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['visit_date' => 'date'];
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
