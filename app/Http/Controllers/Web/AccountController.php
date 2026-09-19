<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function show()
    {
        $user = auth()->user()->load('branch');

        return view('admin.account.show', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        $changed = array_keys(array_filter($data, fn ($value, $key) => $user->{$key} !== $value, ARRAY_FILTER_USE_BOTH));
        activity()->causedBy($user)->performedOn($user)->withProperties(['changed_fields' => $changed])->log('Account profile updated');

        return redirect()->route('admin.account.show')->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        activity()->causedBy($user)->performedOn($user)->log('Password changed');

        return redirect()->route('admin.account.show')->with('success', 'Your password has been changed.');
    }
}