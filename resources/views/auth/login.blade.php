<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#003f20">
    <title>Sign in - VATI Microfinance</title>
    <link rel="stylesheet" href="{{ asset('css/vati.css') }}">
</head>
<body class="auth-page">
    <section class="auth-visual" aria-label="VATI operations portal overview">
        <a class="brand light auth-brand" href="{{ route('home') }}" aria-label="VATI Microfinance home">
            <span class="brand-mark">V</span>
            <span>
                <strong>VATI</strong>
                <small>Microfinance Limited</small>
            </span>
        </a>

        <div class="auth-message">
            <span class="auth-kicker"><i></i> STAFF OPERATIONS PORTAL</span>
            <h1>Run member lending with clarity and control.</h1>
            <p>Access applications, approvals, collections, compliance records, and branch portfolio activity from one secured workspace.</p>

            <div class="auth-metrics" aria-label="Portal capabilities">
                <div>
                    <span>01</span>
                    <strong>Member onboarding</strong>
                    <small>KYC, groups, nominees, and security accounts</small>
                </div>
                <div>
                    <span>02</span>
                    <strong>Loan workflows</strong>
                    <small>Review, approval, disbursement, and collections</small>
                </div>
                <div>
                    <span>03</span>
                    <strong>Audit ready</strong>
                    <small>Role-based access and transaction history</small>
                </div>
            </div>
        </div>

        <footer class="auth-caption">
            <span><i class="status-dot"></i> Secure system online</span>
            <span>{{ now()->format('d M Y') }}</span>
        </footer>
    </section>

    <main class="auth-form-wrap">
        <a class="auth-mobile-brand" href="{{ route('home') }}" aria-label="VATI Microfinance home">
            <span class="brand-mark">V</span>
            <span>VATI</span>
        </a>

        <form class="auth-form" method="POST" action="{{ route('login.store') }}" id="login-form" novalidate>
            @csrf

            <div class="auth-form-head">
                <p class="eyebrow">SECURE SIGN IN</p>
                <h2>Welcome back</h2>
                <p>Use your staff account to continue to the VATI admin portal.</p>
            </div>

            @if($errors->any())
                <div class="alert alert-danger auth-alert" role="alert">
                    <span aria-hidden="true">!</span>
                    <div>
                        <strong>Sign-in unsuccessful</strong>
                        <p>{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <div class="field-group @error('email') invalid @enderror">
                <label for="email">Email address</label>
                <div class="input-shell">
                    <span class="input-icon email-icon" aria-hidden="true"></span>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="name@vati.co.tz" autocomplete="username" inputmode="email" required autofocus>
                </div>
                @error('email')<small>{{ $message }}</small>@else<small>Enter the email assigned to your staff account.</small>@enderror
            </div>

            <div class="field-group @error('password') invalid @enderror">
                <div class="label-row">
                    <label for="password">Password</label>
                    <span id="caps-warning" aria-live="polite">Caps Lock is on</span>
                </div>
                <div class="input-shell">
                    <span class="input-icon lock-icon" aria-hidden="true"></span>
                    <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" id="password-toggle" aria-label="Show password" aria-pressed="false">
                        <span class="eye" aria-hidden="true"></span>
                        <span class="toggle-text">Show</span>
                    </button>
                </div>
                @error('password')<small>{{ $message }}</small>@enderror
            </div>

            <div class="auth-options">
                <label class="check">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>Keep me signed in</span>
                </label>
                <span class="trusted-device">Use only on a trusted device</span>
            </div>

            <button class="btn btn-primary btn-block auth-submit" type="submit" id="login-button">
                <span class="button-label">Sign in to portal</span>
                <span class="button-arrow" aria-hidden="true">-&gt;</span>
            </button>

            <div class="security-note">
                <span class="shield" aria-hidden="true"></span>
                <p>
                    <strong>Protected staff access</strong>
                    <small>Sessions are secured and activity is recorded for audit.</small>
                </p>
            </div>

            <p class="form-foot">Need access restored? Contact your VATI system administrator.</p>
        </form>

        <footer class="auth-legal">&copy; {{ now()->year }} VATI Microfinance Limited. Authorized personnel only.</footer>
    </main>

    <script>
        const password = document.getElementById('password');
        const toggle = document.getElementById('password-toggle');
        const capsWarning = document.getElementById('caps-warning');
        const form = document.getElementById('login-form');
        const submit = document.getElementById('login-button');

        toggle.addEventListener('click', () => {
            const showing = password.type === 'text';
            password.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', String(!showing));
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            toggle.querySelector('.toggle-text').textContent = showing ? 'Show' : 'Hide';
            password.focus();
        });

        password.addEventListener('keyup', event => {
            capsWarning.classList.toggle('visible', event.getModifierState('CapsLock'));
        });

        password.addEventListener('blur', () => {
            capsWarning.classList.remove('visible');
        });

        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            submit.disabled = true;
            submit.querySelector('.button-label').textContent = 'Signing in...';
            submit.querySelector('.button-arrow').textContent = '';
        });
    </script>
</body>
</html>
