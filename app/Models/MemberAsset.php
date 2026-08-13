<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }
}
