@extends('layouts.app')

@section('title', 'Reset Password')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/verify-otp.css') }}?v={{ filemtime(public_path('css/verify-otp.css')) }}" />
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 px-3 py-4 content-wrap auth-centered-wrap">
    <div class="glass-card w-100" style="max-width: 520px;">

      <div class="card-header-bar" style="justify-content:flex-start;">
        <div class="d-flex align-items-center gap-3">
          <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="logo-img" />
          <div>
            <p class="mb-0 fw-semibold logo-name">NU Clark</p>
            <p class="mb-0 logo-tagline">Disciplinary Case Management System</p>
          </div>
        </div>
      </div>

      <div class="card-body-content">
        <div class="otp-icon-wrap mx-auto mb-3">
          <i class="bi bi-key-fill"></i>
        </div>

        <h1 class="form-heading">Create a new password</h1>
        <p class="form-subtext mb-4">
          Use a strong password with at least eight characters, including letters and numbers.
        </p>

        @if ($errors->any())
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
          @csrf

          <input type="hidden" name="token" value="{{ $token }}">

          <label class="pw-field-label" for="emailInput">Registered Email</label>
          <input
            type="email"
            name="email"
            id="emailInput"
            class="pw-field @error('email') is-invalid @enderror"
            value="{{ old('email', $email) }}"
            placeholder="Enter your registered email address"
            autocomplete="email"
            required
          />

          <label class="pw-field-label" for="pw1">New Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pw1" class="pw-field @error('password') is-invalid @enderror"
              placeholder="At least 8 characters" autocomplete="new-password" required autofocus />
            <button type="button" class="pw-toggle" onclick="togglePw('pw1','eye1')" aria-label="Toggle password visibility">
              <i class="bi bi-eye" id="eye1"></i>
            </button>
          </div>

          <label class="pw-field-label" for="pw2">Confirm Password</label>
          <div class="pw-wrap">
            <input type="password" name="password_confirmation" id="pw2" class="pw-field"
              placeholder="Re-enter your password" autocomplete="new-password" required />
            <button type="button" class="pw-toggle" onclick="togglePw('pw2','eye2')" aria-label="Toggle password visibility">
              <i class="bi bi-eye" id="eye2"></i>
            </button>
          </div>

          <p class="pw-note">The reset link can only be used once and expires based on system policy.</p>

          <button type="submit" class="btn-proceed">Reset Password</button>
        </form>
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
  function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }
</script>
@endsection
