<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'admission_date' => 'date', 'passbook_issue_date' => 'date'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function group()
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    public function groupMemberships()
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function activeGroupMembership()
    {
        return $this->hasOne(GroupMembership::class)->where('status', 'active')->whereNull('left_at');
    }

    public function kyc()
    {
        return $this->hasOne(MemberKyc::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function securityAccount()
    {
        return $this->hasOne(MemberSecurityAccount::class);
    }

    public function nominees()
    {
        return $this->hasMany(MemberNominee::class);
    }

    public function passbookReplacements()
    {
        return $this->hasMany(PassbookReplacement::class);
    }
}
