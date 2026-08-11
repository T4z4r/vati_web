<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
