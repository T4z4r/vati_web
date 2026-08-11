<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_number' => $this->membership_number,
            'full_name' => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'physical_address' => $this->physical_address,
            'joined_at' => $this->admission_date,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'branch' => $this->whenLoaded('branch'),
            'group' => $this->whenLoaded('group'),
            'kyc' => $this->whenLoaded('kyc'),
            'active_group_membership' => $this->whenLoaded('activeGroupMembership'),
            'nominees' => $this->whenLoaded('nominees'),
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
