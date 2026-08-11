<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use App\Models\Member;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LoanGroupWitnessController extends ApiController
{
    public function index(LoanApplication $loanApplication)
    {
        $witnesses = $loanApplication->groupWitnesses()->with(['member', 'recordedBy'])->latest('confirmed_at')->get();

        return response()->json([
            'success' => true,
            'data' => $witnesses,
            'meta' => [
                'required' => $loanApplication->product->required_group_witnesses,
                'confirmed' => $witnesses->whereNotNull('confirmed_at')->count(),
            ],
        ]);
    }

    public function store(Request $request, LoanApplication $loanApplication)
    {
        $data = $request->validate([
            'member_id' => [
                'required',
                'exists:members,id',
                Rule::unique('loan_group_witnesses')->where('loan_application_id', $loanApplication->id),
            ],
            'signature_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $witness = DB::transaction(function () use ($data, $loanApplication, $request) {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($loanApplication->id);
            if (! in_array($application->status, [ApplicationStatus::DRAFT, ApplicationStatus::SUBMITTED, ApplicationStatus::LO_REVIEW, ApplicationStatus::ABM_REVIEW, ApplicationStatus::BM_REVIEW, ApplicationStatus::CREDIT_REVIEW], true)) {
                throw new DomainException('Witnesses cannot be added in the current application state.');
            }

            $member = Member::with('activeGroupMembership')->findOrFail($data['member_id']);
            if ($member->id === $application->member_id) {
                throw new DomainException('The borrower cannot witness their own application.');
            }
            if ($member->status !== 'active' || $member->group_id !== $application->group_id || $member->activeGroupMembership?->group_id !== $application->group_id) {
                throw new DomainException('The witness must be an active member of the borrower’s originating group.');
            }

            $witness = $application->groupWitnesses()->create([
                'group_id' => $application->group_id,
                'member_id' => $member->id,
                'signature_path' => $data['signature_path'] ?? null,
                'confirmed_at' => now(),
                'recorded_by' => $request->user()->id,
            ]);
            activity()->causedBy($request->user())->performedOn($application)->withProperties(['witness_member_id' => $member->id])->log('Group witness confirmed');

            return $witness;
        });

        return response()->json(['success' => true, 'message' => 'Group witness confirmed.', 'data' => $witness->load('member', 'recordedBy')], 201);
    }
}
