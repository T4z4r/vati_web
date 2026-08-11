<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function groups()
    {
        return $this->hasMany(MemberGroup::class);
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }
}
