<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class LoanCancellationService
{
    public function cancel(LoanApplication $application, User $user, ?string $reason = null): void
    {
        DB::transaction(function () use ($application, $user, $reason) {
            $application = LoanApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->loan?->disbursement()->exists()) {
                throw new DomainException('A disbursed loan cannot be cancelled through the cooling-off workflow.');
            }
            if (! $application->cancellation_deadline || now()->isAfter($application->cancellation_deadline)) {
                throw new DomainException('The three-day cancellation period has expired.');
            }
            if ($application->status === ApplicationStatus::CANCELLED) {
                throw new DomainException('This application is already cancelled.');
            }

            $application->cancellation()->create(['reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $user->id]);
            $application->update(['status' => ApplicationStatus::CANCELLED]);
            activity()->causedBy($user)->performedOn($application)->log('Loan application cancelled during cooling-off period');
        });
    }
}
