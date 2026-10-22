@php
    $user = auth()->user();
    $activeNav = '';
    $coordActiveNav = '';
    $passwordLayout = match ($user?->role) {
        'super_admin' => 'layouts.admin',
        'discipline_coordinator' => 'layouts.coordinator',
        'discipline_officer' => 'layouts.staff',
        'reporting_user' => 'layouts.reporting-user',
        'guidance_officer' => 'layouts.guidance',
        'us_supervisor' => 'layouts.staff',
        'student' => 'layouts.student',
        default => 'layouts.app',
    };
@endphp

@extends($passwordLayout)

@section('title', 'Change Password')

@section('css')
<style>
    .account-password-wrap { width: 100%; max-width: none; margin: 0; }
    .account-password-card { width: 100%; max-width: 680px; margin: 30px auto 0; overflow: hidden; }
    .account-password-head {
        padding: 23px 26px;
        border-bottom: 1px solid var(--border);
        background: #fbfcff;
    }
    .account-password-title { margin: 0; color: #0f172a; font-size: 1rem; font-weight: 700; }
    .account-password-sub { margin: 4px 0 0; color: #64748b; font-size: .78rem; line-height: 1.5; }
    .account-password-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
        padding: 29px 26px;
    }
    .account-password-field { position: relative; }
    .account-password-field .prism-input { padding-right: 44px; }
    .account-password-toggle {
        position: absolute;
        right: 6px;
        bottom: 5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #64748b;
    }
    .account-password-toggle:hover { background: #eef2f8; color: #3156c7; }
    .account-password-requirements {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 11px 12px;
        border: 1px solid #dbe5f5;
        border-radius: 8px;
        background: #f8faff;
        color: #52637d;
        font-size: .74rem;
        line-height: 1.5;
    }
    .account-password-requirements i { margin-top: 1px; color: #3156c7; }
    .account-password-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 9px;
        padding: 18px 26px;
        border-top: 1px solid var(--border);
        background: #fbfcff;
    }
    @media (max-width: 767.98px) {
        .account-password-card { margin-top: 20px; }
    }
    @media (max-width: 575.98px) {
        .account-password-actions { align-items: stretch; flex-direction: column-reverse; }
        .account-password-actions .prism-btn { justify-content: center; width: 100%; }

        .account-password-head { padding: 18px 18px; }
        .account-password-otp-body { padding: 22px 18px; }
        .account-password-otp-inputs { gap: clamp(6px, 2.4vw, 14px); margin: 6px 0 24px; }
        .account-password-otp-verify .prism-btn { width: 100%; min-width: 0; }
    }

    .account-password-otp-sub { margin: 4px 0 0; color: #64748b; font-size: .78rem; line-height: 1.5; }
    .account-password-otp-body { padding: 40px 34px 34px; }
    {{--
        A grid of six 1fr columns can never overflow its container, unlike fixed-px boxes in
        a flex row (which don't shrink below their set width and forced the page wider than
        the viewport on narrow phones). Each box scales down with the available width instead.
    --}}
    .account-password-otp-inputs {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 18px;
        max-width: 510px;
        margin: 6px auto 32px;
    }
    .account-password-otp-digit {
        width: 100%;
        min-width: 0;
        aspect-ratio: 6 / 7;
        border: 1px solid #d7dfec;
        border-radius: 12px;
        background: #f8faff;
        text-align: center;
        font-size: clamp(1.1rem, 6vw, 2.1rem);
        font-weight: 700;
        color: #0f172a;
        padding: 0;
    }
    .account-password-otp-digit:focus {
        outline: none;
        border-color: #3156c7;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(49, 86, 199, .15);
    }
    .account-password-otp-verify {
        display: flex;
        justify-content: center;
    }
    .account-password-otp-verify .prism-btn {
        width: 100%;
        max-width: 510px;
        padding-top: 12px;
        padding-bottom: 12px;
        justify-content: center;
    }
    .account-password-otp-helper {
        display: block;
        text-align: center;
        margin-top: 22px;
        font-size: .82rem;
        color: #64748b;
    }
    .account-password-otp-resend {
        border: 0;
        background: none;
        padding: 0;
        color: #3156c7;
        font-weight: 600;
        font-size: .82rem;
    }
    .account-password-otp-resend:disabled { color: #9aa7bd; cursor: not-allowed; }
    .account-password-otp-countdown { font-size: .82rem; color: #64748b; }
    .account-password-otp-cancel { display: block; text-align: center; margin-top: 10px; }
    .account-password-otp-debug {
        margin: 0 auto 24px;
        padding: 11px 14px;
        border: 1px solid #ead18a;
        border-radius: 8px;
        background: #fff9e8;
        color: #6e5618;
        text-align: center;
        font-size: .82rem;
    }
    .account-password-otp-debug strong { margin-left: 5px; letter-spacing: .12em; }
</style>
@endsection

@section('content')
@php
    $passwordToastMessages = collect($errors->toArray())->flatten()->unique();
@endphp
{{--
    Rendered through window.PrismToast (see @section('scripts') below) instead of as static
    markup, so a validation failure on this page gets the exact same timing, dedup and
    dismiss behaviour as everywhere else in the app - one implementation, not two.
--}}
@if($passwordToastMessages->isNotEmpty())
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            @foreach($passwordToastMessages as $message)
                window.PrismToast?.show(@json($message), 'danger');
            @endforeach
        });
    </script>
@endif

<div class="prism-content account-password-wrap">
    <div class="prism-page-header">
        <h1 class="prism-page-title">Change Password</h1>
        <p class="prism-page-sub">Update the password used to access your PRISM account.</p>
    </div>

    @if($otpPending)
        <div class="prism-card account-password-card">
            <div class="account-password-head">
                <h2 class="account-password-title">Verify it's You</h2>
                <p class="account-password-otp-sub">We sent a six-digit code to your registered email address. It expires in {{ $otpPending['expiresInMinutes'] }} minutes.</p>
            </div>

            <div class="account-password-otp-body">
                @if($otpPending['debugOtp'])
                    <div class="account-password-otp-debug" role="status">
                        <span>Development OTP:</span>
                        <strong>{{ $otpPending['debugOtp'] }}</strong>
                    </div>
                @endif

                <form method="POST" action="{{ route('account.password.otp.verify') }}" id="passwordOtpForm">
                    @csrf
                    <input type="hidden" name="otp" id="passwordOtpValue" value="">
                    <div class="account-password-otp-inputs" role="group" aria-label="Six-digit verification code">
                        @for($index = 0; $index < 6; $index++)
                            <input class="account-password-otp-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                                   autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                                   aria-label="Digit {{ $index + 1 }}" {{ $index === 0 ? 'autofocus' : '' }}>
                        @endfor
                    </div>
                    <div class="account-password-otp-verify">
                        <button type="submit" class="prism-btn prism-btn-primary">Verify</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('account.password.otp.resend') }}">
                    @csrf
                    <span class="account-password-otp-helper">
                        Didn't get a code?
                        <button type="submit" class="account-password-otp-resend" id="passwordOtpResend"
                                data-available-at="{{ $otpPending['resendAvailableAt'] }}">Resend code</button>
                        <span class="account-password-otp-countdown" id="passwordOtpCountdown" aria-live="polite"></span>
                    </span>
                </form>

                <form method="POST" action="{{ route('account.password.otp.cancel') }}" class="account-password-otp-cancel">
                    @csrf
                    <button type="submit" class="account-password-otp-resend">Cancel</button>
                </form>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('account.password.update') }}" class="prism-card account-password-card">
            @csrf

            <div class="account-password-head">
                <h2 class="account-password-title">Create a new password</h2>
                <p class="account-password-sub">Choose a password that is secure and different from your student number.</p>
            </div>

            <div class="account-password-body">
                <div class="account-password-field full">
                    <label class="prism-label" for="currentPassword">Current Password</label>
                    <input type="password" name="current_password" id="currentPassword"
                           class="prism-input @error('current_password') is-invalid @enderror"
                           placeholder="Enter your current password" required autofocus autocomplete="current-password">
                    <button type="button" class="account-password-toggle" data-password-toggle="currentPassword" aria-label="Show current password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="account-password-field">
                    <label class="prism-label" for="newPassword">New Password</label>
                    <input type="password" name="password" id="newPassword"
                           class="prism-input @error('password') is-invalid @enderror"
                           placeholder="Enter at least 8 characters" required autocomplete="new-password">
                    <button type="button" class="account-password-toggle" data-password-toggle="newPassword" aria-label="Show new password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="account-password-field">
                    <label class="prism-label" for="confirmPassword">Confirm New Password</label>
                    <input type="password" name="password_confirmation" id="confirmPassword"
                           class="prism-input" placeholder="Re-enter your new password" required autocomplete="new-password">
                    <button type="button" class="account-password-toggle" data-password-toggle="confirmPassword" aria-label="Show confirmed password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="account-password-requirements">
                    <i class="bi bi-info-circle"></i>
                    <span>Use at least 8 characters with at least one letter and one number. We'll email you a verification code to confirm the change.</span>
                </div>
            </div>

            <div class="account-password-actions">
                <a href="{{ route('dashboard.profile') }}" class="prism-btn prism-btn-outline">Back</a>
                <button type="submit" class="prism-btn prism-btn-primary">Update Password</button>
            </div>
        </form>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const icon = button.querySelector('i');
        const isHidden = input.type === 'password';

        input.type = isHidden ? 'text' : 'password';
        icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
});

@if($otpPending)
(() => {
    const inputs = Array.from(document.querySelectorAll('.account-password-otp-digit'));
    const hiddenInput = document.getElementById('passwordOtpValue');
    const form = document.getElementById('passwordOtpForm');
    const syncCode = () => { hiddenInput.value = inputs.map(input => input.value).join(''); };

    inputs.forEach((input, index) => {
        input.addEventListener('input', event => {
            const digits = event.target.value.replace(/\D/g, '');
            if (digits.length > 1) {
                digits.slice(0, 6).split('').forEach((digit, offset) => {
                    if (inputs[index + offset]) inputs[index + offset].value = digit;
                });
                inputs[Math.min(index + digits.length, inputs.length - 1)].focus();
            } else {
                event.target.value = digits;
                if (digits && inputs[index + 1]) inputs[index + 1].focus();
            }
            syncCode();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !input.value && inputs[index - 1]) inputs[index - 1].focus();
            if (event.key === 'ArrowLeft' && inputs[index - 1]) inputs[index - 1].focus();
            if (event.key === 'ArrowRight' && inputs[index + 1]) inputs[index + 1].focus();
        });
        input.addEventListener('paste', event => {
            const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            if (!digits) return;
            event.preventDefault();
            digits.split('').forEach((digit, offset) => { if (inputs[offset]) inputs[offset].value = digit; });
            syncCode();
            inputs[Math.min(digits.length, inputs.length) - 1].focus();
        });
    });

    form.addEventListener('submit', syncCode);

    const resendButton = document.getElementById('passwordOtpResend');
    const countdown = document.getElementById('passwordOtpCountdown');
    const updateResend = () => {
        const seconds = Math.max(0, Number(resendButton.dataset.availableAt) - Math.floor(Date.now() / 1000));
        resendButton.disabled = seconds > 0;
        countdown.textContent = seconds > 0 ? ` ${seconds}s` : '';
        if (seconds > 0) window.setTimeout(updateResend, 1000);
    };
    updateResend();

    @if($otpPending['debugOtp'])
        console.info('PRISM development password-change OTP:', @json($otpPending['debugOtp']));
    @endif
})();
@endif
</script>
@endsection
