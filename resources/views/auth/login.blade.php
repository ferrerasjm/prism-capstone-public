@extends('layouts.app')

@section('title', 'Sign In')

@section('body-class', 'auth-login-page')
@section('skip-shared-css', true)
@section('skip-shared-js', true)

@section('css')
  <link rel="preload" as="image" href="{{ asset('assets/Student.webp') }}" type="image/webp" fetchpriority="high" />
  <link rel="stylesheet" href="{{ asset('css/login.css') }}@if(file_exists(public_path('css/login.css')))?v={{ filemtime(public_path('css/login.css')) }}@endif" />
  <link rel="stylesheet" href="{{ asset('css/privacy-notice.css') }}?v={{ filemtime(public_path('css/privacy-notice.css')) }}" />
@endsection

@section('content')
@php
  $loginToastMessages = collect($errors->toArray())
    ->except('g-recaptcha-response')
    ->flatten()
    ->unique();
@endphp
{{--
    Rendered through window.PrismToast (see @section('scripts') below) instead of as static
    markup, so a validation failure on this page gets the exact same timing, dedup and
    dismiss behaviour as everywhere else in the app - one implementation, not two.
--}}
@if($loginToastMessages->isNotEmpty())
  <script>
    window.addEventListener('DOMContentLoaded', function () {
      @foreach($loginToastMessages as $message)
        window.PrismToast?.show(@json($message), 'danger');
      @endforeach
    });
  </script>
@endif

<div class="page-bg">

  <picture>
    <source srcset="{{ asset('assets/vector1_left.webp') }}" type="image/webp">
    <img src="{{ asset('assets/vector1_left.png') }}" width="763" height="790" alt="" class="vec-left" aria-hidden="true" decoding="async" fetchpriority="low" />
  </picture>
  <picture>
    <source srcset="{{ asset('assets/vector2_right.webp') }}" type="image/webp">
    <img src="{{ asset('assets/vector2_right.png') }}" width="720" height="614" alt="" class="vec-right" aria-hidden="true" decoding="async" fetchpriority="low" />
  </picture>

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 w-100 px-3 py-4 content-wrap auth-centered-wrap login-content-wrap">
    <div class="login-card-stage w-100 mx-auto d-flex justify-content-center">
      <div class="glass-card w-100 mx-auto login-glass-card" style="max-width: 1012px; margin-left: auto; margin-right: auto;">
      <div class="row g-0 card-inner">

        <div class="col-md-5 d-flex flex-column align-items-center align-items-md-start p-4 pt-md-5 ps-md-5 auth-brand-panel">
          <div class="d-flex align-items-center gap-3 mb-4 auth-brand-row">
            <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" width="376" height="376" alt="PRISM logo" class="logo-img" decoding="async" fetchpriority="high" />
            <div class="text-white auth-brand-copy">
              <p class="mb-0 fw-semibold logo-name">NU Clark</p>
              <p class="mb-0 logo-tagline">Disciplinary Case Management System</p>
            </div>
          </div>

          <div class="flex-grow-1 d-flex align-items-center justify-content-center w-100 mt-3">
            <div class="position-relative illus-wrap">
              <picture>
                <source srcset="{{ asset('assets/Student.webp') }}" type="image/webp">
                <img src="{{ asset('assets/Student.png') }}" width="400" height="481" alt="Student reading" class="student-img" decoding="async" fetchpriority="high" />
              </picture>
              <div class="ellipse-shadow">
                <svg viewBox="0 0 275 96" fill="none" width="100%">
                  <ellipse cx="137.5" cy="48" rx="137.5" ry="48" fill="url(#eGrad)" />
                  <defs>
                    <linearGradient id="eGrad" x1="137.5" x2="137.5" y1="0" y2="96" gradientUnits="userSpaceOnUse">
                      <stop stop-color="#243E8D" />
                      <stop offset="1" stop-color="#6179BF" />
                    </linearGradient>
                  </defs>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-7 d-flex flex-column justify-content-center px-4 px-md-5 pb-3 pb-md-0 auth-form-panel">
          <h1 class="text-white text-center fw-bold form-title mb-1">Sign In</h1>
          <p class="text-white text-center mb-4 form-subtitle">Login to continue to your account.</p>

          <form method="POST" action="{{ route('login.post') }}" id="loginForm" novalidate>
            @csrf

            <div class="mb-4">
              <label class="form-label text-white fw-semibold field-label" for="emailInput">
                Registered Email
              </label>
              <input
                type="email"
                name="email"
                class="form-control custom-input{{ $errors->has('email') ? ' is-invalid' : '' }}"
                placeholder="Enter your email address"
                id="emailInput"
                value="{{ old('email') }}"
                autocomplete="email"
                @if($errors->has('email')) aria-invalid="true" @endif
              />
            </div>

            <div class="mb-2">
              <label class="form-label text-white fw-semibold field-label" for="passwordInput">
                Password
              </label>
              <div class="position-relative">
                <input
                  type="password"
                  name="password"
                  class="form-control custom-input pe-5{{ $errors->has('password') ? ' is-invalid' : '' }}"
                  placeholder="Enter your password"
                  id="passwordInput"
                  autocomplete="current-password"
                  @if($errors->has('password')) aria-invalid="true" @endif
                />
                <button type="button" class="eye-btn position-absolute top-50 end-0 translate-middle-y me-2" id="togglePassword" aria-label="Toggle password visibility">
                  <i class="bi bi-eye-slash" id="eyeIcon"></i>
                </button>
              </div>
            </div>

            <div class="text-end mb-4">
              <a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>
            </div>

            @if(config('services.recaptcha.enabled'))
              <div class="captcha-verification-group mb-4">
                <div class="captcha-verification-viewport" id="recaptchaViewport">
                  <div id="recaptchaContainer"></div>
                </div>
                <div class="captcha-verification-error d-none" id="captchaClientError" role="alert">
                  <span class="captcha-verification-error-icon" aria-hidden="true">
                    <i class="bi bi-exclamation-circle-fill"></i>
                  </span>
                  <span id="captchaClientErrorText"></span>
                </div>
                @error('g-recaptcha-response')
                  <div class="captcha-verification-error" role="alert">
                    <span class="captcha-verification-error-icon" aria-hidden="true">
                      <i class="bi bi-exclamation-circle-fill"></i>
                    </span>
                    <span>{{ $message }}</span>
                  </div>
                @enderror
              </div>
            @endif

            <button type="submit" class="btn-signin">Sign in</button>

          </form>

        </div>

      </div>
      </div>
    </div>

    <p class="text-white mt-4 text-center fst-italic fw-semibold disclaimer">
      *This system contains confidential student disciplinary records. Unauthorized access is strictly prohibited.
    </p>
  </div>
</div>

<div class="privacy-modal-backdrop{{ ($openPrivacyModal ?? false) ? '' : ' d-none' }}" id="privacyModalBackdrop">
  @include('auth.partials.privacy-modal-card')
</div>
@endsection

@section('scripts')
@if(config('services.recaptcha.enabled'))
<script>
  // reCAPTCHA is a fixed 304px; scale it to fill the container instead of using its cramped compact layout.
  window.onRecaptchaLoad = function () {
    var container = document.getElementById('recaptchaContainer');
    if (!container || typeof grecaptcha === 'undefined') return;

    var viewport = document.getElementById('recaptchaViewport');
    var MOBILE_BREAKPOINT = 767;
    var CAPTCHA_WIDTH = 304;
    var CAPTCHA_HEIGHT = 78;

    function fitCaptchaWidth() {
      if (!viewport) return;

      container.style.transform = '';
      viewport.style.height = '';

      if (window.innerWidth > MOBILE_BREAKPOINT) {
        viewport.style.width = CAPTCHA_WIDTH + 'px';
        return;
      }

      viewport.style.width = '100%';
      var availableWidth = viewport.clientWidth;
      if (!availableWidth) return;

      var scale = availableWidth / CAPTCHA_WIDTH;
      container.style.transform = 'scale(' + scale + ')';
      viewport.style.height = Math.ceil(CAPTCHA_HEIGHT * scale) + 'px';
    }

    function setCaptchaError(message) {
      var error = document.getElementById('captchaClientError');
      var errorText = document.getElementById('captchaClientErrorText');
      if (!error || !errorText) return;
      errorText.textContent = message;
      error.classList.toggle('d-none', !message);
    }

    window.setLoginCaptchaError = setCaptchaError;

    window.loginRecaptchaWidgetId = grecaptcha.render(container, {
      sitekey: '{{ config('services.recaptcha.site_key') }}',
      theme: 'light',
      size: 'normal',
      callback: function () {
        setCaptchaError('');
      },
      'expired-callback': function () {
        setCaptchaError('Robot verification expired. Please complete it again.');
      },
      'error-callback': function () {
        setCaptchaError('Robot verification could not load. Check your connection and try again.');
      }
    });

    fitCaptchaWidth();
    setTimeout(fitCaptchaWidth, 300);

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(fitCaptchaWidth, 150);
    });
  };
</script>
<script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit" async defer></script>
@endif
<script>
(function () {
  // Toasts on this page go through window.PrismToast (public/js/prism-toast.js, loaded
  // unconditionally in layouts.app) rather than a login-specific implementation - that
  // shared helper carries its own dismiss timer, so it works correctly even though this
  // page deliberately skips bootstrap.bundle.min.js for a faster first paint.
  function showLoginErrorToast(message) {
    window.PrismToast?.show(message, 'danger');
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function markFieldInvalid(inputId) {
    const input = document.getElementById(inputId);

    if (input) {
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
    }
  }

  function clearFieldError(inputId) {
    const input = document.getElementById(inputId);

    if (input) {
      input.classList.remove('is-invalid');
      input.setAttribute('aria-invalid', 'false');
    }
  }

  const togglePasswordBtn = document.getElementById('togglePassword');
  if (togglePasswordBtn) {
    togglePasswordBtn.addEventListener('click', function (e) {
      e.preventDefault();
      const input = document.getElementById('passwordInput');
      const icon = document.getElementById('eyeIcon');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye';
      } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-slash';
      }
    });
  }

  const emailInput = document.getElementById('emailInput');
  const passwordInput = document.getElementById('passwordInput');

  if (emailInput) {
    emailInput.addEventListener('input', function () {
      clearFieldError('emailInput');
    });

    emailInput.addEventListener('blur', function () {
      const value = this.value.trim();
      if (value && !isValidEmail(value)) {
        markFieldInvalid('emailInput');
      }
    });
  }

  if (passwordInput) {
    passwordInput.addEventListener('input', function () {
      clearFieldError('passwordInput');
    });
  }

  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      const email = (emailInput.value || '').trim();
      const password = passwordInput.value || '';
      let isValid = true;

      clearFieldError('emailInput');
      clearFieldError('passwordInput');

      if (!email && !password) {
        markFieldInvalid('emailInput');
        markFieldInvalid('passwordInput');
        showLoginErrorToast('Email and password are required');
        isValid = false;
      } else {
        if (!email) {
          markFieldInvalid('emailInput');
          showLoginErrorToast('Email is required');
          isValid = false;
        } else if (!isValidEmail(email)) {
          markFieldInvalid('emailInput');
          showLoginErrorToast('Please enter a valid email address');
          isValid = false;
        }

        if (!password) {
          markFieldInvalid('passwordInput');
          showLoginErrorToast('Password is required');
          isValid = false;
        }
      }

      @if(config('services.recaptcha.enabled'))
      const captchaReady = typeof grecaptcha !== 'undefined' && window.loginRecaptchaWidgetId !== undefined;
      const captchaResponse = captchaReady ? grecaptcha.getResponse(window.loginRecaptchaWidgetId) : '';

      if (!captchaResponse) {
        window.setLoginCaptchaError?.(captchaReady
          ? 'Please complete the "I\'m not a robot" verification.'
          : 'Robot verification is still loading. Please wait and try again.');
        isValid = false;
      } else {
        window.setLoginCaptchaError?.('');
      }
      @endif

      if (!isValid) {
        e.preventDefault();
        window.PrismPageLoader?.stop();
        if (!email || !isValidEmail(email)) {
          emailInput.focus();
        } else if (!password) {
          passwordInput.focus();
        }
        return;
      }

      const submitButton = loginForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Signing in...';
      }
    });
  }

})();
</script>
@include('auth.partials.privacy-modal-script')
@endsection
