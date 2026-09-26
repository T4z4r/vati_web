<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'reversed_at' => 'datetime', 'client_created_at' => 'datetime', 'server_received_at' => 'datetime'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
