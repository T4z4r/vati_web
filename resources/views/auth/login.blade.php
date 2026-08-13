<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#062e1d">
    <meta name="color-scheme" content="light">
    <title>Sign in | VATI Microfinance</title>
    <link rel="icon" type="image/png" href="{{ asset('images/vati_app_icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/phosphor.css') }}">
    <link rel="stylesheet" href="{{ asset('css/form-loading.css') }}">
    <style>
        :root {
            --forest-950: #052418;
            --forest-900: #073522;
            --forest-800: #0a4b2e;
            --forest-700: #0b6139;
            --forest-100: #dff2e7;
            --gold: #d6aa3e;
            --gold-soft: #f7edcf;
            --ink: #17251d;
            --muted: #68766d;
            --line: #dce5df;
            --surface: #ffffff;
            --danger: #b42318;
            --danger-soft: #fff0ee;
            --shadow: 0 28px 80px rgba(6, 46, 29, .16);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: #edf3ef;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button,
        input {
            font: inherit;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .login-page {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(440px, .92fr);
            overflow: hidden;
        }

        .story-panel {
            position: relative;
            isolation: isolate;
            min-height: 100vh;
            padding: 42px clamp(42px, 6vw, 92px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
            overflow: hidden;
            background:
                radial-gradient(circle at 78% 24%, rgba(214, 170, 62, .19), transparent 28%),
                linear-gradient(145deg, var(--forest-950), var(--forest-800));
        }

        .story-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            opacity: .4;
            background-image:
                linear-gradient(rgba(255, 255, 255, .045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .045) 1px, transparent 1px);
            background-size: 52px 52px;
            mask-image: linear-gradient(120deg, #000 20%, transparent 84%);
        }

        .story-panel::after {
            content: "";
            position: absolute;
            z-index: -1;
            width: 520px;
            height: 520px;
            right: -260px;
            bottom: -220px;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 50%;
            box-shadow: 0 0 0 80px rgba(255, 255, 255, .025), 0 0 0 160px rgba(214, 170, 62, .035);
        }

        .brand {
            width: fit-content;
            display: inline-flex;
            align-items: center;
            gap: 13px;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: var(--forest-950);
            background: linear-gradient(145deg, #f1d47b, var(--gold));
            box-shadow: 0 12px 32px rgba(0, 0, 0, .2), inset 0 1px rgba(255, 255, 255, .5);
            font-family: Georgia, serif;
            font-size: 26px;
            font-weight: 800;
        }

        img.brand-mark {
            object-fit: contain;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }

        .brand-copy strong,
        .brand-copy small {
            display: block;
        }

        .brand-copy strong {
            font-size: 18px;
            letter-spacing: .16em;
        }

        .brand-copy small {
            margin-top: 3px;
            color: #b8d0c1;
            font-size: 10px;
            letter-spacing: .055em;
        }

        .story-content {
            max-width: 680px;
            margin: auto 0;
            padding: 54px 0;
        }

        .portal-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            color: #edca68;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .19em;
        }

        .portal-label::before {
            content: "";
            width: 32px;
            height: 1px;
            background: currentColor;
        }

        .story-content h1 {
            max-width: 640px;
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(46px, 5vw, 72px);
            font-weight: 500;
            line-height: .98;
            letter-spacing: -.045em;
        }

        .story-content h1 span {
            color: #edca68;
        }

        .story-lead {
            max-width: 555px;
            margin: 26px 0 34px;
            color: #c6dacf;
            font-size: 14px;
            line-height: 1.75;
        }

        .journey {
            max-width: 650px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .journey-item {
            min-height: 132px;
            padding: 17px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 15px;
            background: rgba(255, 255, 255, .055);
            backdrop-filter: blur(8px);
        }

        .journey-number {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            margin-bottom: 22px;
            border: 1px solid rgba(237, 202, 104, .4);
            border-radius: 9px;
            color: #edca68;
            font-size: 9px;
            font-weight: 800;
        }

        .journey-item strong,
        .journey-item small {
            display: block;
        }

        .journey-item strong {
            font-size: 12px;
        }

        .journey-item small {
            margin-top: 7px;
            color: #a9c4b4;
            font-size: 10px;
            line-height: 1.5;
        }

        .story-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #9fbbaa;
            font-size: 10px;
        }

        .system-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .system-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #5bd38b;
            box-shadow: 0 0 0 5px rgba(91, 211, 139, .1);
        }

        .lang-switch {
            position: absolute;
            top: 42px;
            right: clamp(42px, 6vw, 92px);
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 2px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 20px;
            padding: 3px;
        }

        .lang-switch a {
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            color: #cfe3d7;
        }

        .lang-switch a.active {
            background: var(--gold);
            color: var(--forest-950);
        }

        .lang-switch-mobile {
            display: none;
        }

        .access-panel {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 56px clamp(32px, 6vw, 88px) 72px;
            background:
                radial-gradient(circle at 100% 0, rgba(214, 170, 62, .1), transparent 30%),
                linear-gradient(180deg, #f8faf8, #eef4f0);
            overflow-y: auto;
        }

        .mobile-brand {
            display: none;
        }

        .login-card {
            width: min(450px, 100%);
            padding: clamp(28px, 4vw, 42px);
            border: 1px solid rgba(210, 222, 214, .9);
            border-radius: 24px;
            background: rgba(255, 255, 255, .94);
            box-shadow: var(--shadow);
        }

        .card-topline {
            width: 42px;
            height: 4px;
            margin-bottom: 28px;
            border-radius: 999px;
            background: var(--gold);
        }

        .form-heading {
            margin-bottom: 28px;
        }

        .form-heading .eyebrow {
            margin: 0 0 9px;
            color: var(--forest-700);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .18em;
        }

        .form-heading h2 {
            margin: 0;
            font-size: 32px;
            line-height: 1.15;
            letter-spacing: -.035em;
        }

        .form-heading p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.65;
        }

        .alert {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            margin-bottom: 22px;
            padding: 13px;
            border: 1px solid #f2c9c4;
            border-radius: 12px;
            color: #8d2118;
            background: var(--danger-soft);
        }

        .alert-mark {
            flex: 0 0 24px;
            height: 24px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            background: var(--danger);
            font-size: 11px;
            font-weight: 800;
        }

        .alert strong,
        .alert p {
            display: block;
            margin: 0;
        }

        .alert strong {
            font-size: 11px;
        }

        .alert p {
            margin-top: 3px;
            font-size: 10px;
            line-height: 1.4;
        }

        .field {
            margin-top: 19px;
        }

        .label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .field label {
            color: #304038;
            font-size: 11px;
            font-weight: 700;
        }

        #caps-warning {
            visibility: hidden;
            color: #93640c;
            font-size: 9px;
            font-weight: 700;
        }

        #caps-warning.visible {
            visibility: visible;
        }

        .control {
            position: relative;
        }

        .control-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            width: 18px;
            height: 18px;
            transform: translateY(-50%);
            color: #7a8a80;
            font-size: 20px;
            pointer-events: none;
        }

        .control input {
            width: 100%;
            height: 52px;
            margin: 0;
            padding: 0 92px 0 46px;
            border: 1px solid #cbd8cf;
            border-radius: 12px;
            outline: none;
            color: var(--ink);
            background: #fbfdfb;
            font-size: 13px;
            transition: border-color .18s, box-shadow .18s, background .18s;
        }

        .control input[type="email"] {
            padding-right: 15px;
        }

        .control input::placeholder {
            color: #9ba79f;
        }

        .control input:hover {
            border-color: #a9bbb0;
        }

        .control input:focus {
            border-color: var(--forest-700);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(11, 97, 57, .09);
        }

        .field.invalid .control input {
            border-color: #d55c52;
        }

        .field-help {
            display: block;
            min-height: 14px;
            margin: 6px 2px 0;
            color: #7d8a82;
            font-size: 9px;
            line-height: 1.45;
        }

        .field.invalid .field-help {
            color: var(--danger);
        }

        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            height: 34px;
            width: 36px;
            padding: 0;
            transform: translateY(-50%);
            border: 0;
            border-radius: 8px;
            color: #68766d;
            background: transparent;
            cursor: pointer;
        }

        .password-toggle .ph {
            font-size: 20px;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            color: var(--forest-700);
            background: var(--forest-100);
            outline: none;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin: 18px 0;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #4f5f55;
            font-size: 11px;
            cursor: pointer;
        }

        .remember input {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: var(--forest-700);
        }

        .device-note {
            color: #89958d;
            font-size: 9px;
        }

        .submit-button {
            width: 100%;
            height: 52px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 18px;
            border: 0;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(135deg, var(--forest-800), var(--forest-700));
            box-shadow: 0 13px 28px rgba(7, 75, 46, .23);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .18s, box-shadow .18s;
        }

        .submit-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 34px rgba(7, 75, 46, .28);
        }

        .submit-button:focus-visible {
            outline: 3px solid rgba(214, 170, 62, .45);
            outline-offset: 3px;
        }

        .submit-button:disabled {
            opacity: .7;
            cursor: wait;
            transform: none;
        }

        .submit-arrow {
            font-size: 17px;
            line-height: 1;
        }

        .secure-note {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 21px;
            padding: 13px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fbf9;
        }

        .secure-note>.ph {
            flex: 0 0 27px;
            color: var(--forest-700);
            font-size: 27px;
        }

        .secure-note strong,
        .secure-note small {
            display: block;
        }

        .secure-note strong {
            font-size: 10px;
        }

        .secure-note small {
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.4;
        }

        .support {
            margin: 17px 0 0;
            color: var(--muted);
            text-align: center;
            font-size: 10px;
            line-height: 1.5;
        }

        .legal {
            position: absolute;
            bottom: 22px;
            left: 30px;
            right: 30px;
            color: #7f8d84;
            text-align: center;
            font-size: 9px;
        }

        @media (max-width: 1000px) {
            .login-page {
                grid-template-columns: minmax(350px, .78fr) minmax(420px, 1.22fr);
            }

            .story-panel {
                padding: 34px;
            }

            .story-content h1 {
                font-size: 44px;
            }

            .journey {
                grid-template-columns: 1fr;
            }

            .journey-item {
                min-height: auto;
                display: grid;
                grid-template-columns: 36px 1fr;
                column-gap: 10px;
            }

            .journey-number {
                grid-row: 1 / 3;
                margin: 0;
            }

            .journey-item small {
                margin-top: 4px;
            }

            .journey-item:nth-child(3) {
                display: none;
            }
        }

        @media (max-width: 760px) {
            .login-page {
                display: block;
                min-height: 100vh;
            }

            .story-panel {
                display: none;
            }

            .access-panel {
                min-height: 100vh;
                min-height: 100dvh;
                padding: 92px 20px 64px;
            }

            .mobile-brand {
                position: absolute;
                top: 24px;
                left: 22px;
                display: inline-flex;
                align-items: center;
                gap: 9px;
                color: var(--forest-900);
                font-size: 16px;
                font-weight: 800;
                letter-spacing: .12em;
            }

            .mobile-brand .brand-mark {
                width: 34px;
                height: 34px;
                border-radius: 10px;
                font-size: 19px;
            }

            .login-card {
                padding: 28px 22px;
                border-radius: 20px;
            }

            .form-heading h2 {
                font-size: 29px;
            }

            .legal {
                position: absolute;
                bottom: 17px;
            }

            .lang-switch-mobile {
                display: flex;
                position: absolute;
                top: 24px;
                right: 22px;
                align-items: center;
                gap: 2px;
                border: 1px solid var(--line);
                border-radius: 20px;
                padding: 3px;
                background: #fff;
            }

            .lang-switch-mobile a {
                padding: 5px 11px;
                border-radius: 16px;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: .04em;
                color: var(--muted);
            }

            .lang-switch-mobile a.active {
                background: var(--forest-700);
                color: #fff;
            }
        }

        @media (max-width: 390px) {
            .access-panel {
                padding-left: 14px;
                padding-right: 14px;
            }

            .form-options {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }

            .device-note {
                padding-left: 25px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>
    <main class="login-page">
        <section class="story-panel" aria-label="VATI operations portal overview">
            <div class="lang-switch" role="group" aria-label="Language">
                <a href="{{ route('locale.switch', 'sw') }}"
                    class="{{ app()->getLocale() === 'sw' ? 'active' : '' }}">SW</a>
                <a href="{{ route('locale.switch', 'en') }}"
                    class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
            </div>
            <a class="brand" href="{{ route('home') }}" aria-label="VATI Microfinance home">
                <img class="brand-mark" src="{{ asset('images/vati_app_icon_foreground.png') }}" alt="VATI logo">
                <span class="brand-copy"><strong>VATI</strong><small>Microfinance Limited</small></span>
            </a>

            <div class="story-content">
                <span class="portal-label">{{ __('STAFF OPERATIONS PORTAL') }}</span>
                <h1>{{ __('One platform.') }}<br><span>{{ __('Every member journey.') }}</span></h1>
                <p class="story-lead">
                    {{ __('Manage member onboarding, responsible lending, repayments, and compliance from one secure operational workspace.') }}
                </p>

                <div class="journey" aria-label="Core portal capabilities">
                    <div class="journey-item">
                        <span class="journey-number">01</span>
                        <strong>{{ __('Know every member') }}</strong>
                        <small>{{ __('Profiles, KYC, groups, nominees, and passbooks.') }}</small>
                    </div>
                    <div class="journey-item">
                        <span class="journey-number">02</span>
                        <strong>{{ __('Lend with confidence') }}</strong>
                        <small>{{ __('Applications, approvals, disbursements, and security.') }}</small>
                    </div>
                    <div class="journey-item">
                        <span class="journey-number">03</span>
                        <strong>{{ __('Stay audit ready') }}</strong>
                        <small>{{ __('Collections, clearances, controls, and activity history.') }}</small>
                    </div>
                </div>
            </div>

            <footer class="story-footer">
                <span class="system-status"><i class="system-dot" aria-hidden="true"></i>
                    {{ __('Secure system online') }}</span>
                <span>{{ now()->format('d M Y') }}</span>
            </footer>
        </section>

        <section class="access-panel" aria-label="Staff sign in">
            <div class="lang-switch-mobile" role="group" aria-label="Language">
                <a href="{{ route('locale.switch', 'sw') }}"
                    class="{{ app()->getLocale() === 'sw' ? 'active' : '' }}">SW</a>
                <a href="{{ route('locale.switch', 'en') }}"
                    class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
            </div>
            <a class="mobile-brand" href="{{ route('home') }}" aria-label="VATI Microfinance home">
                <img class="brand-mark" src="{{ asset('images/vati_app_icon.png') }}" alt="VATI logo"><span>VATI</span>
            </a>

            <form class="login-card" method="POST" action="{{ route('login.store') }}" id="login-form" data-no-loading
                novalidate>
                @csrf
                <div class="card-topline" aria-hidden="true"></div>

                <header class="form-heading">
                    <p class="eyebrow">{{ __('AUTHORIZED STAFF ACCESS') }}</p>
                    <h2>{{ __('Welcome back') }}</h2>
                    <p>{{ __('Sign in with your VATI staff credentials to continue.') }}</p>
                </header>

                @if ($errors->any())
                    <div class="alert" role="alert">
                        <span class="alert-mark" aria-hidden="true">!</span>
                        <div><strong>{{ __('Sign-in unsuccessful') }}</strong>
                            <p>{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif

                <div class="field @error('email') invalid @enderror">
                    <div class="label-row"><label for="email">{{ __('Email address') }}</label></div>
                    <div class="control">
                        <span class="ph ph-envelope-simple control-icon" aria-hidden="true"></span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            placeholder="name@vati.co.tz" autocomplete="username" inputmode="email" required autofocus>
                    </div>
                    <small class="field-help">
                        @error('email')
                            {{ $message }}
                        @else
                            {{ __('Enter the email assigned to your staff account.') }}
                        @enderror
                    </small>
                </div>

                <div class="field @error('password') invalid @enderror">
                    <div class="label-row"><label for="password">{{ __('Password') }}</label><span id="caps-warning"
                            aria-live="polite">{{ __('Caps Lock is on') }}</span></div>
                    <div class="control">
                        <span class="ph ph-lock-key control-icon" aria-hidden="true"></span>
                        <input id="password" type="password" name="password"
                            placeholder="{{ __('Enter your password') }}" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" id="password-toggle"
                            aria-label="{{ __('Show password') }}" aria-pressed="false"><span class="ph ph-eye toggle-icon"
                                aria-hidden="true"></span></button>
                    </div>
                    <small class="field-help">
                        @error('password')
                            {{ $message }}
                        @enderror
                    </small>
                </div>

                <div class="form-options">
                    <label class="remember"><input type="checkbox" name="remember" value="1"
                            @checked(old('remember'))><span>{{ __('Keep me signed in') }}</span></label>
                    <span class="device-note">{{ __('Trusted devices only') }}</span>
                </div>

                <button class="submit-button" type="submit" id="login-button">
                    <span class="button-label">{{ __('Sign in to portal') }}</span><span class="ph ph-arrow-right submit-arrow" aria-hidden="true"></span>
                </button>

                <div class="secure-note">
                    <span class="ph ph-shield-check" aria-hidden="true"></span>
                    <span><strong>{{ __('Protected staff access') }}</strong><small>{{ __('Your session and account activity are secured and recorded.') }}</small></span>
                </div>

                <p class="support">{{ __('Need access restored? Contact your VATI system administrator.') }}</p>
            </form>

            <footer class="legal">&copy; {{ now()->year }} VATI Microfinance Limited.
                {{ __('Authorized personnel only.') }}</footer>
        </section>
    </main>

    <script>
        (() => {
            const form = document.getElementById('login-form');
            const password = document.getElementById('password');
            const toggle = document.getElementById('password-toggle');
            const capsWarning = document.getElementById('caps-warning');
            const submit = document.getElementById('login-button');

            toggle.addEventListener('click', () => {
                const willShow = password.type === 'password';
                password.type = willShow ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(willShow));
                toggle.setAttribute('aria-label', willShow ? @json(__('Hide password')) :
                    @json(__('Show password')));
                toggle.querySelector('.toggle-icon').classList.toggle('ph-eye-slash', willShow);
                toggle.querySelector('.toggle-icon').classList.toggle('ph-eye', !willShow);
                password.focus();
            });

            const updateCapsLock = event => capsWarning.classList.toggle('visible', event.getModifierState?.(
                'CapsLock') ?? false);
            password.addEventListener('keydown', updateCapsLock);
            password.addEventListener('keyup', updateCapsLock);
            password.addEventListener('blur', () => capsWarning.classList.remove('visible'));

            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                    return;
                }

                submit.disabled = true;
                submit.insertAdjacentHTML('afterbegin', '<span class="btn-spinner" aria-hidden="true"></span>');
                submit.querySelector('.button-label').textContent = @json(__('Signing in...'));
                submit.querySelector('.submit-arrow').remove();
            });
        })();
    </script>
</body>

</html>
