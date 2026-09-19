@extends('layouts.admin')
@section('title', __('My Account'))
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('MY ACCOUNT') }}</p>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">{{ __('Back to dashboard') }}</a>
        </div>
    </div>

    <div class="grid-2 grid-even">
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Profile details') }}</h2>
            </div>
            <div class="card-body detail-grid" style="grid-template-columns:1fr 1fr">
                <div class="detail">
                    <small>{{ __('Full name') }}</small><strong>{{ $user->name }}</strong>
                </div>
                <div class="detail">
                    <small>{{ __('Email address') }}</small><strong>{{ $user->email }}</strong>
                </div>
                <div class="detail">
                    <small>{{ __('Role') }}</small><strong>{{ ucwords(str_replace('_', ' ', $user->roles->first()?->name ?? __('Unassigned'))) }}</strong>
                </div>
                <div class="detail">
                    <small>{{ __('Branch') }}</small><strong>{{ $user->branch?->branch_name ?? __('Organization-wide') }}</strong>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2>{{ __('Security') }}</h2><span>{{ __('10 characters minimum') }}</span>
            </div>
            <form class="card-body form-grid" method="POST" action="{{ route('admin.account.password') }}">
                @csrf
                @method('PUT')
                <label>{{ __('Current password') }}<span class="pwd-field"><input type="password"
                            name="current_password" required autocomplete="current-password"><button type="button"
                                class="password-toggle js-toggle-password" aria-label="{{ __('Show password') }}"
                                aria-pressed="false"><span class="ph ph-eye toggle-icon" aria-hidden="true"></span></button></span></label>
                <label>{{ __('New password') }}<span class="pwd-field"><input type="password" name="password"
                            minlength="10" required autocomplete="new-password"><button type="button"
                                class="password-toggle js-toggle-password" aria-label="{{ __('Show password') }}"
                                aria-pressed="false"><span class="ph ph-eye toggle-icon" aria-hidden="true"></span></button></span></label>
                <label>{{ __('Confirm new password') }}<span class="pwd-field"><input type="password"
                            name="password_confirmation" minlength="10" required
                            autocomplete="new-password"><button type="button"
                                class="password-toggle js-toggle-password" aria-label="{{ __('Show password') }}"
                                aria-pressed="false"><span class="ph ph-eye toggle-icon" aria-hidden="true"></span></button></span></label>
                <div class="full form-actions">
                    <button class="btn btn-primary">{{ __('Change password') }}</button>
                </div>
            </form>
        </div>
    </div>

    <form class="card" method="POST" action="{{ route('admin.account.update') }}">
        @csrf
        @method('PUT')
        <div class="card-head">
            <h2>{{ __('Update profile information') }}</h2>
        </div>
        <div class="card-body form-grid">
            <label>{{ __('Full name') }}<input name="name" value="{{ old('name', $user->name) }}" required></label>
            <label>{{ __('Email address') }}<input type="email" name="email" value="{{ old('email', $user->email) }}"
                    required></label>
            <div class="full form-actions">
                <button class="btn btn-primary">{{ __('Save changes') }}</button>
            </div>
        </div>
    </form>
@endsection