<?php

namespace App\Services;

use App\Models\GroupCollection;
use App\Models\GroupMeeting;
use App\Models\GroupMembership;
use App\Models\GroupVisit;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGroupWitness;
use App\Models\Member;
use App\Models\MemberGroup;
use DomainException;

class GroupDeletionService
{
    /**
     * Permanently delete a group, but only while nothing references it.
     *
     * Deletion is refused as soon as any record is still linked to the group so
     * that members, loans, applications, repayments and other financial history
     * are never cascade-deleted behind the user's back. Callers must surface the
     * exception message to the user and keep the group in place.
     *
     * @throws DomainException when the group still has linked records.
     */
    public function forceDelete(MemberGroup $group): void
    {
        if ($blockers = $this->blockers($group)) {
            throw new DomainException('This group cannot be deleted because it still has '.implode(', ', $blockers).'. Remove or reassign them first.');
        }

        $group->forceDelete();
    }

    /**
     * @return array<int, string> Labels of the records that block deletion.
     */
    private function blockers(MemberGroup $group): array
    {
        $blockers = [];

        if (Member::withTrashed()->where('group_id', $group->id)->exists()) {
            $blockers[] = 'members';
        }

        if (Loan::where('group_id', $group->id)->exists()) {
            $blockers[] = 'loans';
        }

        if (LoanApplication::withTrashed()->where('group_id', $group->id)->exists()) {
            $blockers[] = 'loan applications';
        }

        if (LoanGroupWitness::where('group_id', $group->id)->exists()) {
            $blockers[] = 'recorded witnesses';
        }

        if (GroupMembership::where('group_id', $group->id)->exists()) {
            $blockers[] = 'membership records';
        }

        if (GroupMeeting::where('group_id', $group->id)->exists()) {
            $blockers[] = 'meetings';
        }

        if (GroupCollection::where('group_id', $group->id)->exists()) {
            $blockers[] = 'collections';
        }

        if (GroupVisit::where('group_id', $group->id)->exists()) {
            $blockers[] = 'recorded visits';
        }

        return $blockers;
    }
}
