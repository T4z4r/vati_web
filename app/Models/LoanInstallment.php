<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoanInstallment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function scopeUnpaidPastDue(Builder $query): Builder
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', ['paid', 'waived'])
            ->whereRaw('total_due - total_paid - interest_exemption > 0');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
