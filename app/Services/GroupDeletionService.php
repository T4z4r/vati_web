<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberGroup;
use DomainException;
use Illuminate\Support\Facades\DB;

class GroupDeletionService
{
    public function __construct(private readonly MemberDeletionService $memberDeletion)
    {
    }

    /**
     * Permanently delete a group that has no live members or recorded visits.
     *
     * Soft-deleted members that still point at the group are force-deleted
     * with their linked records, and residual rows (left-over memberships,
     * witnesses, meetings, collections, or orphan loans/applications) are
     * purged in a single transaction so the restrictive foreign keys are
     * satisfied before the group row is force-deleted. Live members and their
     * loans are never touched.
     *
     * @throws DomainException when the group has members or visits.
     */
    public function forceDelete(MemberGroup $group): void
    {
        if ($group->members()->exists() || $group->visits()->exists()) {
            throw new DomainException('This group has members or recorded visits and cannot be deleted.');
        }

        DB::transaction(function () use ($group) {
            Member::onlyTrashed()->where('group_id', $group->id)->get()->each(
                fn (Member $member) => $this->memberDeletion->forceDelete($member)
            );

            $loanIds = DB::table('loans')->where('group_id', $group->id)->pluck('id');
            if ($loanIds->isNotEmpty()) {
                DB::table('loan_refinancings')->whereIn('old_loan_id', $loanIds)->orWhereIn('new_loan_id', $loanIds)->delete();
                DB::table('loan_settlements')->whereIn('loan_id', $loanIds)->delete();
                DB::table('loan_disbursements')->whereIn('loan_id', $loanIds)->delete();
                DB::table('payments')->whereIn('loan_id', $loanIds)->delete();
                DB::table('security_transactions')->whereIn('loan_id', $loanIds)->delete();
                DB::table('loans')->whereIn('id', $loanIds)->delete();
            }
            DB::table('loan_applications')->where('group_id', $group->id)->delete();
            DB::table('loan_group_witnesses')->where('group_id', $group->id)->delete();
            DB::table('group_memberships')->where('group_id', $group->id)->delete();
            DB::table('group_collections')->where('group_id', $group->id)->delete();
            DB::table('group_meetings')->where('group_id', $group->id)->delete();
            $group->forceDelete();
        });
    }
}