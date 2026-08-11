<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGroupWitness extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    public function application() { return $this->belongsTo(LoanApplication::class, 'loan_application_id'); }

    public function group() { return $this->belongsTo(MemberGroup::class); }

    public function member() { return $this->belongsTo(Member::class); }

    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
