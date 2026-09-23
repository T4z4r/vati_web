<?php

namespace App\Services;

use App\Models\MemberGroup;
use DomainException;
use Illuminate\Support\Facades\DB;

class GroupDeletionService
{
    /**
     * Permanently delete a group that has no members or recorded visits.
     *
     * Every other referencing record (memberships, loans, loan applications,
     * witnesses, meetings, collections) is derived from members, so an empty
     * group holds none of them. Residual collections and visits are removed in
     * a single transaction to satisfy the restrictive foreign keys before the
     * group row is force-deleted.
     *
     * @throws DomainException when the group has members or visits.
     */
    public function forceDelete(MemberGroup $group): void
    {
        if ($group->members()->exists() || $group->visits()->exists()) {
            throw new DomainException('This group has members or recorded visits and cannot be deleted.');
        }

        DB::transaction(function () use ($group) {
            DB::table('group_collections')->where('group_id', $group->id)->delete();
            $group->visits()->delete();
            $group->forceDelete();
        });
    }
}