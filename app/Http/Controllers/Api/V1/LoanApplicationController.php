<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreLoanApplicationRequest;
use App\Http\Resources\LoanApplicationResource;
use App\Models\LoanApplication;
use App\Models\Member;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends ApiController
{
    public function index(Request $request)
    {
        $query = $this->branchScope(LoanApplication::with('member', 'product'), $request)->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->member_id, fn ($q, $v) => $q->where('member_id', $v));

        return LoanApplicationResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreLoanApplicationRequest $request, NumberGeneratorService $numbers)
    {
        $application = DB::transaction(function () use ($request, $numbers) {
            $data = $request->validated();
            $assessment = Arr::pull($data, 'assessment');
            $member = Member::with(['group', 'activeGroupMembership'])->lockForUpdate()->findOrFail($data['member_id']);
            $user = $request->user();
            abort_if(! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id && $member->branch_id !== $user->branch_id, 403, 'You cannot create an application for another branch.');
            abort_unless($member->status === 'active', 422, 'Only active members can apply for a loan.');
            abort_unless($member->group && $member->group->status, 422, 'Member must belong to an active group.');
            abort_unless($member->activeGroupMembership?->group_id === $member->group_id, 422, 'Member does not have a matching active group membership.');
            abort_unless($member->group->branch_id === $member->branch_id, 422, 'Member group and branch do not match.');
            $application = LoanApplication::create([...$data, 'group_id' => $member->group_id, 'branch_id' => $member->branch_id, 'application_number' => $numbers->application(), 'status' => 'draft', 'created_by' => $request->user()->id]);
            if ($assessment) {
                $income = ($assessment['core_business_income'] ?? 0) + ($assessment['other_income'] ?? 0);
                $expenses = ($assessment['business_expenses'] ?? 0) + ($assessment['household_expenses'] ?? 0);
                $application->assessment()->create([...$assessment, 'monthly_profit' => $income - ($assessment['business_expenses'] ?? 0), 'disposable_income' => $income - $expenses]);
            }
            activity()->causedBy($request->user())->performedOn($application)->log('Loan application created');

            return $application;
        });

        return response()->json(['success' => true, 'message' => 'Loan application created.', 'data' => new LoanApplicationResource($application->load('member', 'product', 'assessment'))], 201);
    }

    public function show(LoanApplication $loanApplication)
    {
        return response()->json(['success' => true, 'data' => new LoanApplicationResource($loanApplication->load('member.nominees', 'product', 'assessment', 'approvals.user', 'loan', 'term', 'guarantors', 'documents', 'cancellation'))]);
    }

    public function update(StoreLoanApplicationRequest $request, LoanApplication $loanApplication)
    {
        abort_unless($loanApplication->status->value === 'draft', 409, 'Only draft applications can be updated.');
        $loanApplication->update(Arr::except($request->validated(), ['assessment', 'member_id']));

        return response()->json(['success' => true, 'data' => new LoanApplicationResource($loanApplication->refresh())]);
    }

    public function destroy(LoanApplication $loanApplication)
    {
        abort_unless($loanApplication->status->value === 'draft', 409, 'Only draft applications can be deleted.');
        $loanApplication->delete();

        return response()->noContent();
    }
}
