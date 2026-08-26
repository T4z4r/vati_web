<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string', 'max:100']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->status || ! Hash::check($data['password'], $user->password)) {
            activity()->useLog('auth')->withProperties(['email' => $data['email'], 'ip' => $request->ip()])->log('Failed login attempt');
            throw ValidationException::withMessages(['email' => ['Invalid login credentials.']]);
        }
        $token = $user->createToken($data['device_name'] ?? 'api-client')->plainTextToken;
        activity()->useLog('auth')->causedBy($user)->performedOn($user)->withProperties(['ip' => $request->ip(), 'device_name' => $data['device_name'] ?? null])->log('User logged in');

        return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user, 'roles' => $user->getRoleNames(), 'permissions' => $user->getAllPermissions()->pluck('name')]]);
    }

    public function profile(Request $request)
    {
        return response()->json(['success' => true, 'data' => $request->user()->load('branch')]);
    }

    public function logout(Request $request)
    {
        activity()->useLog('auth')->causedBy($request->user())->withProperties(['ip' => $request->ip()])->log('User logged out');
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => $data['email']]);
        activity()->useLog('auth')->withProperties(['email' => $data['email'], 'ip' => $request->ip()])->log('Password reset link requested');

        // A neutral response prevents account enumeration.
        return response()->json(['success' => true, 'message' => 'If an account exists for that email address, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        $resetUser = null;
        $status = Password::reset($data, function (User $user, string $password) use (&$resetUser) {
            $resetUser = $user;
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
        activity()->useLog('auth')->causedBy($resetUser)->performedOn($resetUser)->withProperties(['ip' => $request->ip()])->log('Password reset completed');

        return response()->json(['success' => true, 'message' => 'Password reset successfully. Please sign in with your new password.']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $user->tokens()->delete();
        activity()->useLog('auth')->causedBy($user)->performedOn($user)->withProperties(['ip' => $request->ip()])->log('Password changed');

        return response()->json(['success' => true, 'message' => 'Password changed successfully. Please sign in again.']);
    }
}
