<?php

namespace App\Services;

use App\Models\GroupMembership;
use App\Models\Member;
use App\Models\MemberGroup;
use DomainException;
use Illuminate\Support\Facades\DB;

class GroupMembershipService
{
    public function assign(Member $member, MemberGroup $group, mixed $joinedAt = null): GroupMembership
    {
        return DB::transaction(function () use ($member, $group, $joinedAt) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);
            $group = MemberGroup::query()->lockForUpdate()->findOrFail($group->id);

            if (! $group->status) {
                throw new DomainException('Members can only be assigned to an active group.');
            }

            $active = GroupMembership::query()
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($active?->group_id === $group->id) {
                if ($member->group_id !== $group->id || $member->branch_id !== $group->branch_id) {
                    $member->update(['group_id' => $group->id, 'branch_id' => $group->branch_id]);
                }

                return $active;
            }

            if ($active) {
                $active->update(['status' => 'inactive', 'left_at' => today()]);
            }

            $membership = GroupMembership::create([
                'member_id' => $member->id,
                'group_id' => $group->id,
                'joined_at' => $joinedAt ?? today(),
                'status' => 'active',
            ]);
            $member->update(['group_id' => $group->id, 'branch_id' => $group->branch_id]);

            return $membership;
        });
    }
}
