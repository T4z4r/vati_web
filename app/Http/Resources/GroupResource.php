<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_code' => $this->group_code,
            'group_name' => $this->group_name,
            'meeting_day' => $this->meeting_day,
            'meeting_time' => $this->meeting_time ? substr($this->meeting_time, 0, 5) : null,
            'region' => $this->region,
            'district' => $this->district,
            'ward' => $this->ward,
            'location' => $this->location,
            'status' => $this->status,
            'members_count' => $this->whenCounted('members'),
            'loans_count' => $this->whenCounted('loans'),
            'loan_applications_count' => $this->whenCounted('loanApplications'),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'branch_code' => $this->branch->branch_code,
                'branch_name' => $this->branch->branch_name,
                'phone' => $this->branch->phone,
                'email' => $this->branch->email,
                'address' => $this->branch->address,
            ]),
            'loan_officer' => $this->whenLoaded('loanOfficer', fn () => [
                'id' => $this->loanOfficer->id,
                'name' => $this->loanOfficer->name,
            ]),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                'id' => $m->id,
                'membership_number' => $m->membership_number,
                'first_name' => $m->first_name,
                'last_name' => $m->last_name,
                'phone' => $m->phone,
                'photo_url' => $m->photo_path ? asset('storage/' . $m->photo_path) : null,
                'status' => $m->status,
                'current_loans_count' => $m->current_loans_count ?? 0,
                'outstanding_loan_balance' => $m->outstanding_loan_balance ?? 0,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
