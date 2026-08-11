<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Models\CreditReview;
use App\Models\LoanApplication;
use App\Services\PortfolioAnalyticsService;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private readonly PortfolioAnalyticsService $portfolio) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $user = $request->user();
        $branchId = $this->portfolio->authorizedBranch($user, $filters['branch_id'] ?? null);
        $roles = $user->getRoleNames();
        $data = [
            'role' => $roles->first(),
            'roles' => $roles,
            'as_of' => now()->toIso8601String(),
        ];

        if ($user->hasRole('credit_officer')) {
            $assigned = LoanApplication::query()->where('assigned_credit_officer_id', $user->id);
            if ($branchId) {
                $assigned->where('branch_id', $branchId);
            }
            $priority = (clone $assigned)->whereIn('status', [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW])
                ->with(['member:id,first_name,last_name,membership_number', 'product:id,name'])
                ->oldest('submitted_at')->limit(10)->get()->map(fn (LoanApplication $application) => [
                    'id' => $application->id,
                    'application_number' => $application->application_number,
                    'member_name' => trim($application->member->first_name.' '.$application->member->last_name),
                    'product' => $application->product->name,
                    'requested_amount' => (string) $application->requested_amount,
                    'status' => $application->status->value,
                    'submitted_at' => $application->submitted_at?->toIso8601String(),
                ]);

            $data['credit_officer'] = [
                'pending_credit_review' => (clone $assigned)->whereIn('status', [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW])->count(),
                'new_assignments_today' => (clone $assigned)->whereDate('updated_at', today())->count(),
                'reviewed_today' => CreditReview::query()->where('reviewed_by', $user->id)->whereDate('reviewed_at', today())->count(),
                'returned_cases' => (clone $assigned)->where('status', ApplicationStatus::RETURNED)->count(),
                'high_risk_cases' => (clone $assigned)->where('risk_level', 'high')->whereIn('status', [ApplicationStatus::CREDIT_REVIEW, ApplicationStatus::RECOMMENDED])->count(),
                'daily_target' => (int) config('vati.credit_daily_target', 10),
                'priority_applications' => $priority,
            ];
        }

        if ($user->hasAnyRole(['super_admin', 'head_office_admin', 'regional_manager', 'area_manager', 'branch_manager', 'assistant_branch_manager'])) {
            $applications = LoanApplication::query();
            if ($branchId) {
                $applications->where('branch_id', $branchId);
            }
            $data['administration'] = [
                'pending_final_approval' => (clone $applications)->where('status', ApplicationStatus::RECOMMENDED)->count(),
                'returned_applications' => (clone $applications)->where('status', ApplicationStatus::RETURNED)->count(),
                'portfolio' => $this->portfolio->summary($user, $branchId, $filters['from'] ?? null, $filters['to'] ?? null),
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
