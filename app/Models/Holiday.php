<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $guarded = [];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = CarbonImmutable::parse($value)->toDateString();
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
