<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        return User::with('branch', 'roles')->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))->latest()->paginate($this->perPage($request));
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        $roles = Arr::pull($data, 'roles', []);
        $user = User::create($data);
        $user->syncRoles($roles);
        activity()->causedBy($request->user())->performedOn($user)->withProperties(['email' => $user->email, 'roles' => $roles, 'branch_id' => $user->branch_id])->log('User account created');

        return response()->json(['success' => true, 'message' => 'User created successfully.', 'data' => $user->load('branch', 'roles')], 201);
    }

    public function show(User $user)
    {
        $user->load(['branch', 'roles', 'permissions', 'attachments.uploadedBy']);

        return response()->json([
            'success' => true,
            'data' => array_merge($user->toArray(), [
                'attachments' => $user->attachments->map(fn ($attachment) => UserAttachmentController::shape($user, $attachment))->values()->all(),
                'audit_trail' => $this->auditTrail($user),
            ]),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->data($request, $user);
        $roles = Arr::pull($data, 'roles');
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $changes = Arr::except(array_keys($data), ['password']);
        $passwordChanged = isset($data['password']);
        $user->update($data);
        if ($roles !== null) {
            $user->syncRoles($roles);
        }
        activity()->causedBy($request->user())->performedOn($user)->withProperties(['changed_fields' => array_values($changes), 'password_changed' => $passwordChanged, 'roles' => $roles])->log('User account updated');

        return response()->json(['success' => true, 'data' => $user->refresh()->load('branch', 'roles')]);
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is(auth()->user()), 409, 'You cannot delete your own account.');
        activity()->causedBy($request->user())->withProperties(['deleted_user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]])->log('User account deleted');
        $user->delete();

        return response()->noContent();
    }

    public function roles()
    {
        return response()->json(['success' => true, 'data' => Role::with('permissions')->get()]);
    }

    private function auditTrail(User $user): array
    {
        return Activity::query()
            ->where(fn ($q) => $q->where('causer_type', User::class)->where('causer_id', $user->id))
            ->orWhere(fn ($q) => $q->where('subject_type', User::class)->where('subject_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'log_name' => $activity->log_name,
                'direction' => $activity->causer_type === User::class && (int) $activity->causer_id === (int) $user->id ? 'performed' : 'on_account',
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'properties' => $activity->properties,
                'performed_by' => $activity->causer ? ['id' => $activity->causer->id, 'name' => $activity->causer->name] : null,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function data(Request $request, ?User $user = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', Rule::unique('users')->ignore($user)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:10'], 'branch_id' => ['nullable', 'exists:branches,id'], 'status' => ['sometimes', 'boolean'], 'roles' => ['sometimes', 'array'], 'roles.*' => ['string', 'exists:roles,name']]);
    }
}
