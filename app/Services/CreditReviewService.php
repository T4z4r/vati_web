<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Exceptions\WorkflowConflictException;
use App\Models\CreditReview;
use App\Models\LoanApplication;
use App\Models\LoanApproval;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreditReviewService
{
    public function __construct(private NotificationService $notifications) {}

    public function assign(LoanApplication $application, User $officer, User $actor): LoanApplication
    {
        if (! in_array($application->status, [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW], true)) {
            throw new WorkflowConflictException('Only a submitted application can be assigned for credit review.');
        }
        if (! $officer->hasRole('credit_officer') || ! $officer->status || ($officer->branch_id && $officer->branch_id !== $application->branch_id)) {
            throw new DomainException('The selected credit officer is not eligible for this application.');
        }
        $application->update(['assigned_credit_officer_id' => $officer->id, 'status' => ApplicationStatus::CREDIT_REVIEW]);
        activity()->causedBy($actor)->performedOn($application)->withProperties(['assigned_credit_officer_id' => $officer->id])->log('Application assigned for credit review');
        $this->notifications->send($officer, 'application_assigned', 'New application assigned', "{$application->application_number} requires credit review.", 'loan_application', $application->id);

        return $application->refresh()->load('assignedCreditOfficer');
    }

    public function review(LoanApplication $application, User $reviewer, array $data): CreditReview
    {
        return DB::transaction(function () use ($application, $reviewer, $data) {
            $application = LoanApplication::with('product')->lockForUpdate()->findOrFail($application->id);
            if (! in_array($application->status, [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW], true)) {
                throw new WorkflowConflictException('Only submitted or credit-review applications can be reviewed.');
            }
            if ($application->assigned_credit_officer_id && $application->assigned_credit_officer_id !== $reviewer->id && ! $reviewer->hasAnyRole(['super_admin', 'head_office_admin'])) {
                throw new DomainException('This application is assigned to another credit officer.');
            }
            if (CreditReview::where('loan_application_id', $application->id)->where('attempt', $application->credit_review_attempt)->exists()) {
                throw new WorkflowConflictException('This credit-review attempt has already been submitted.');
            }
            if ($data['decision'] === 'recommend' && (! $data['member_verified'] || ! $data['group_membership_verified'] || ! $data['documents_verified'])) {
                throw new DomainException('All verification checks must be confirmed before recommending approval.');
            }
            if (($data['recommended_amount'] ?? 0) > (float) $application->requested_amount) {
                throw new DomainException('Recommended amount cannot exceed the requested amount.');
            }
            if (isset($data['recommended_amount']) && (float) $data['recommended_amount'] < (float) $application->product->minimum_amount) {
                throw new DomainException('Recommended amount is below the selected product minimum.');
            }
            if (isset($data['recommended_duration_months']) && ($data['recommended_duration_months'] < $application->product->minimum_duration_months || $data['recommended_duration_months'] > $application->product->maximum_duration_months)) {
                throw new DomainException('Recommended duration is outside the selected product limits.');
            }

            $review = CreditReview::create([...$data, 'loan_application_id' => $application->id, 'attempt' => $application->credit_review_attempt, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            $to = $data['decision'] === 'return' ? ApplicationStatus::RETURNED : ApplicationStatus::RECOMMENDED;
            $application->update([
                'status' => $to,
                'recommended_amount' => $data['recommended_amount'] ?? null,
                'recommended_duration_months' => $data['recommended_duration_months'] ?? null,
                'risk_level' => $data['overall_risk'],
                'assigned_credit_officer_id' => $application->assigned_credit_officer_id ?: $reviewer->id,
            ]);
            activity()->causedBy($reviewer)->performedOn($application)->withProperties(['from' => 'credit_review', 'to' => $to->value, 'decision' => $data['decision'], 'remarks' => $data['remarks'] ?? null])->log('Credit review submitted');

            $recipients = $to === ApplicationStatus::RETURNED ? $this->notifications->applicationOriginators($application) : $this->notifications->applicationApprovers($application);
            $this->notifications->send($recipients, $to === ApplicationStatus::RETURNED ? 'application_returned' : 'credit_recommendation', $to === ApplicationStatus::RETURNED ? 'Application returned for correction' : 'Credit recommendation ready', "{$application->application_number} · {$data['decision']}", 'loan_application', $application->id);

            return $review->load('reviewer');
        });
    }

    public function returnByAdministrator(LoanApplication $application, User $actor, string $remarks): LoanApplication
    {
        return DB::transaction(function () use ($application, $actor, $remarks) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->status !== ApplicationStatus::RECOMMENDED) {
                throw new WorkflowConflictException('Only a recommended application can be returned by an administrator.');
            }
            LoanApproval::create(['loan_application_id' => $application->id, 'user_id' => $actor->id, 'role' => $actor->getRoleNames()->first() ?? 'user', 'decision' => 'returned', 'from_status' => $application->status->value, 'to_status' => ApplicationStatus::RETURNED->value, 'remarks' => $remarks, 'acted_at' => now()]);
            $application->update(['status' => ApplicationStatus::RETURNED]);
            activity()->causedBy($actor)->performedOn($application)->withProperties(['remarks' => $remarks])->log('Application returned by administrator');
            $this->notifications->send($this->notifications->applicationOriginators($application), 'application_returned', 'Application returned for correction', "{$application->application_number} · {$remarks}", 'loan_application', $application->id);

            return $application->refresh();
        });
    }
}
