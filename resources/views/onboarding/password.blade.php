@extends('layouts.app')

@section('title', 'Set Your Password')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/verify-otp.css') }}?v={{ filemtime(public_path('css/verify-otp.css')) }}" />
  <style>
    .step-track {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      margin-bottom: 20px;
    }
    .step-node {
      width: 30px; height: 30px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700;
      border: 2px solid rgba(255,255,255,0.25);
      color: rgba(255,255,255,0.45);
      background: transparent;
      transition: all 0.2s;
    }
    .step-node.active {
      border-color: #93C5FD;
      background: rgba(147,197,253,0.15);
      color: #fff;
    }
    .step-node.done {
      border-color: #4ADE80;
      background: rgba(74,222,128,0.15);
      color: #4ADE80;
    }
    .step-line {
      width: 40px; height: 2px;
      background: rgba(255,255,255,0.15);
    }
    .step-line.done { background: #4ADE80; }
  </style>
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 px-3 py-4 content-wrap auth-centered-wrap">
    <div class="glass-card w-100" style="max-width: 500px;">

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

        @php $isStudent = auth()->user()->role === 'student'; @endphp

        @if($isStudent)
          <div class="step-track">
            <div class="step-node active">1</div>
            <div class="step-line"></div>
            <div class="step-node">2</div>
            <div class="step-line"></div>
            <div class="step-node">3</div>
          </div>
        @endif

        <div class="otp-icon-wrap mx-auto mb-3">
          <i class="bi bi-key-fill"></i>
        </div>

        <h1 class="form-heading">Set Your Password</h1>
        <p class="form-subtext mb-4">
          Your account requires a new password.<br>
          Your student number cannot be used as a password.
        </p>

        @if ($errors->any())
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('onboarding.password.update') }}">
          @csrf

          <label class="pw-field-label">New Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pw1" class="pw-field @error('password') is-invalid @enderror"
              placeholder="At least 8 characters" required autofocus />
            <button type="button" class="pw-toggle" onclick="togglePw('pw1','eye1')">
              <i class="bi bi-eye" id="eye1"></i>
            </button>
          </div>

          <label class="pw-field-label">Confirm Password</label>
          <div class="pw-wrap">
            <input type="password" name="password_confirmation" id="pw2" class="pw-field"
              placeholder="Re-enter your password" required />
            <button type="button" class="pw-toggle" onclick="togglePw('pw2','eye2')">
              <i class="bi bi-eye" id="eye2"></i>
            </button>
          </div>

          <p class="pw-note">Minimum 8 characters with at least one letter and one number.</p>

          <button type="submit" class="btn-proceed">
            {{ $isStudent ? 'Continue' : 'Complete Setup' }}
          </button>
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
    const icon  = document.getElementById(iconId);
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
