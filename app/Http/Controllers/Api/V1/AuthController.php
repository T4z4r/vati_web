<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string', 'max:100']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->status || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid login credentials.']]);
        }
        $token = $user->createToken($data['device_name'] ?? 'api-client')->plainTextToken;

        return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user, 'roles' => $user->getRoleNames(), 'permissions' => $user->getAllPermissions()->pluck('name')]]);
    }

    public function profile(Request $request)
    {
        return response()->json(['success' => true, 'data' => $request->user()->load('branch')]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }
}
