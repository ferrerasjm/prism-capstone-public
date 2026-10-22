@extends('layouts.app')

@section('title', 'Forgot Password')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/verify-otp.css') }}?v={{ filemtime(public_path('css/verify-otp.css')) }}" />
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 px-3 py-4 content-wrap auth-centered-wrap">
    <div class="glass-card w-100" style="max-width: 520px;">

      <div class="card-header-bar auth-brand-header" style="justify-content:flex-start;">
        <div class="d-flex align-items-center gap-3 auth-brand-row">
          <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="logo-img" />
          <div class="auth-brand-copy">
            <p class="mb-0 fw-semibold logo-name">NU Clark</p>
            <p class="mb-0 logo-tagline">Disciplinary Case Management System</p>
          </div>
        </div>
      </div>

      <div class="card-body-content">
        <div class="otp-icon-wrap mx-auto mb-3">
          <i class="bi bi-key-fill" aria-hidden="true"></i>
        </div>

        <h1 class="form-heading">Reset your password</h1>
        <p class="form-subtext mb-4">
          Enter your registered email address. If the account exists, PRISM will send a secure reset link.
        </p>

        @if ($errors->any())
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
          @csrf

          <label class="pw-field-label" for="emailInput">Registered Email</label>
          <input
            type="email"
            name="email"
            id="emailInput"
            class="pw-field @error('email') is-invalid @enderror"
            value="{{ old('email') }}"
            placeholder="Enter your registered email address"
            autocomplete="email"
            required
            autofocus
          />

          <p class="pw-note">
            For security, PRISM shows the same confirmation even if the email is not registered.
          </p>

          <button type="submit" class="btn-proceed">Send Reset Link</button>
        </form>

        <div class="text-center mt-4">
          <a href="{{ route('login') }}" class="text-white fw-semibold text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Back to sign in
          </a>
        </div>
      </div>
    </div>

    <p class="text-white mt-4 text-center fst-italic fw-semibold disclaimer">
      *This system contains confidential student disciplinary records. Unauthorized access is strictly prohibited.
    </p>
  </div>
</div>
@endsection
