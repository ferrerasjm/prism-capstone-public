@extends('layouts.app')

@section('title', 'Verify Login')

@section('body-class', 'auth-login-page')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}" />
  <link rel="stylesheet" href="{{ asset('css/login-otp.css') }}?v={{ filemtime(public_path('css/login-otp.css')) }}" />
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 w-100 px-3 py-4 content-wrap auth-centered-wrap login-content-wrap">
    <div class="login-card-stage w-100 mx-auto d-flex justify-content-center">
      <div class="glass-card w-100 mx-auto login-glass-card" style="max-width: 1012px; margin-left: auto; margin-right: auto;">
        <div class="row g-0 card-inner">
          <div class="col-md-5 d-flex flex-column align-items-center align-items-md-start p-4 pt-md-5 ps-md-5 auth-brand-panel">
            <div class="d-flex align-items-center gap-3 mb-4 auth-brand-row">
              <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="logo-img" />
              <div class="text-white auth-brand-copy">
                <p class="mb-0 fw-semibold logo-name">NU Clark</p>
                <p class="mb-0 logo-tagline">Disciplinary Case Management System</p>
              </div>
            </div>

            <div class="flex-grow-1 d-flex align-items-center justify-content-center w-100 mt-3">
              <div class="position-relative illus-wrap">
                <img src="{{ asset('assets/Student.png') }}" alt="Student reading" class="student-img" />
                <div class="ellipse-shadow">
                  <svg viewBox="0 0 275 96" fill="none" width="100%" aria-hidden="true">
                    <ellipse cx="137.5" cy="48" rx="137.5" ry="48" fill="url(#otpEllipseGradient)" />
                    <defs>
                      <linearGradient id="otpEllipseGradient" x1="137.5" x2="137.5" y1="0" y2="96" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#243E8D" />
                        <stop offset="1" stop-color="#6179BF" />
                      </linearGradient>
                    </defs>
                  </svg>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-7 d-flex flex-column justify-content-center px-4 px-md-5 pb-3 pb-md-0 auth-form-panel login-otp-panel">
            <h1 class="text-white text-center fw-bold form-title mb-1">Enter the code</h1>
            <p class="text-white text-center mb-4 form-subtitle login-otp-subtitle">
              <span class="login-otp-copy-line">We sent a six-digit code to <strong style="white-space:nowrap;">{{ $email }}</strong>.</span>
              <span class="login-otp-copy-line">It expires in {{ $expiresInMinutes }} minutes.</span>
            </p>

            @if($debugOtp)
              <div class="login-otp-debug" role="status">
                <span>Development OTP:</span>
                <strong>{{ $debugOtp }}</strong>
              </div>
            @endif

            <form method="POST" action="{{ route('login.otp.verify') }}" id="otpForm">
              @csrf
              <input type="hidden" name="otp" id="otpValue" value="">
              <div class="login-otp-inputs" role="group" aria-label="Six-digit verification code">
                @for($index = 0; $index < 6; $index++)
                  <input class="login-otp-digit" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                         autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                         aria-label="Digit {{ $index + 1 }}" {{ $index === 0 ? 'autofocus' : '' }}>
                @endfor
              </div>
              <button type="submit" class="btn-signin mt-4">Verify</button>
            </form>

            <form method="POST" action="{{ route('login.otp.resend') }}" class="text-center mt-3">
              @csrf
              <span class="login-otp-helper">Didn't get a code?</span>
              <button type="submit" class="login-otp-resend" id="resendButton"
                      data-available-at="{{ $resendAvailableAt }}">Resend code</button>
              <span class="login-otp-countdown" id="resendCountdown" aria-live="polite"></span>
            </form>

            <div class="text-center mt-3">
              <a href="{{ route('login') }}" class="login-otp-back">
                <i class="bi bi-arrow-left me-1"></i> Back to sign in
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p class="text-white mt-4 text-center fst-italic fw-semibold disclaimer">
      *This system contains confidential student disciplinary records. Unauthorized access is strictly prohibited.
    </p>
  </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
  const inputs = Array.from(document.querySelectorAll('.login-otp-digit'));
  const hiddenInput = document.getElementById('otpValue');
  const form = document.getElementById('otpForm');
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

  const resendButton = document.getElementById('resendButton');
  const countdown = document.getElementById('resendCountdown');
  const updateResend = () => {
    const seconds = Math.max(0, Number(resendButton.dataset.availableAt) - Math.floor(Date.now() / 1000));
    resendButton.disabled = seconds > 0;
    countdown.textContent = seconds > 0 ? ` ${seconds}s` : '';
    if (seconds > 0) window.setTimeout(updateResend, 1000);
  };
  updateResend();

  @if($debugOtp)
    console.info('PRISM development login OTP:', @json($debugOtp));
  @endif
})();
</script>
@endsection
