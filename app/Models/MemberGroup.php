<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function loanOfficer()
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'group_id');
    }

    public function memberships()
    {
        return $this->hasMany(GroupMembership::class, 'group_id');
    }

    public function meetings()
    {
        return $this->hasMany(GroupMeeting::class, 'group_id');
    }

    public function collections()
    {
        return $this->hasMany(GroupCollection::class, 'group_id');
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class, 'group_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'group_id');
    }

    public function visits()
    {
        return $this->hasMany(GroupVisit::class, 'group_id');
    }
}
