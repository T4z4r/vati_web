<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\User;
use App\Notifications\VatiDatabaseNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function send(User|Collection|array|null $recipients, string $type, string $title, string $message, ?string $resourceType = null, ?int $resourceId = null): void
    {
        $users = $recipients instanceof User ? collect([$recipients]) : collect($recipients);
        $users->filter()->unique('id')->each(fn (User $user) => $user->notify(new VatiDatabaseNotification([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ])));
    }

    public function applicationOriginators(LoanApplication $application): Collection
    {
        return User::query()->whereIn('id', array_filter([$application->created_by, $application->assigned_credit_officer_id]))->get();
    }

    public function applicationApprovers(LoanApplication $application): Collection
    {
        return User::query()->where(fn ($query) => $query->where('branch_id', $application->branch_id)->orWhereNull('branch_id'))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['branch_manager', 'credit_officer', 'head_office_admin', 'super_admin']))->get();
    }
}
