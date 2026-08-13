<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }
}
