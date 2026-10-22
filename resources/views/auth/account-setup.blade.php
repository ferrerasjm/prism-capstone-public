@extends('layouts.app')

@section('title', 'Set Up Your Account')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/verify-otp.css') }}?v={{ filemtime(public_path('css/verify-otp.css')) }}" />
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 px-3 py-4 content-wrap auth-centered-wrap">
    <div class="glass-card account-setup-card {{ $invalid ? 'account-setup-card--invalid' : '' }} w-100" style="max-width: {{ $invalid ? '640px' : '520px' }};">
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
          <i class="bi bi-person-check-fill"></i>
        </div>

        @if($invalid)
          <h1 class="form-heading">Setup Link Unavailable</h1>
          <p class="form-subtext mb-4">
            This account setup link is invalid, expired, or has already been used.
          </p>

          <div class="alert alert-danger py-2 px-3 mb-4" role="alert" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>
            Contact the system administrator to request a new setup link.
          </div>

          <a href="{{ route('login') }}" class="btn-proceed account-setup-return text-decoration-none text-center d-block">
            Return to Login
          </a>
        @else
          @php($isStudent = $user->role === 'student')

          @if($isStudent)
            <div class="d-flex align-items-center justify-content-center mb-3" style="gap:0;">
              <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                    style="width:30px;height:30px;border:2px solid #93C5FD;background:rgba(147,197,253,.15);color:#fff;font-size:12px;font-weight:700;">1</span>
              <span style="width:40px;height:2px;background:rgba(255,255,255,.15);"></span>
              <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                    style="width:30px;height:30px;border:2px solid rgba(255,255,255,.25);color:rgba(255,255,255,.45);font-size:12px;font-weight:700;">2</span>
              <span style="width:40px;height:2px;background:rgba(255,255,255,.15);"></span>
              <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                    style="width:30px;height:30px;border:2px solid rgba(255,255,255,.25);color:rgba(255,255,255,.45);font-size:12px;font-weight:700;">3</span>
            </div>
          @endif

          <h1 class="form-heading">Create your password</h1>
          <p class="form-subtext mb-3">
            Activate the PRISM account registered to<br>
            <strong>{{ $user->email }}</strong>.
          </p>
          <p class="pw-note mb-4">
            This one-time link expires {{ $expiresAt->format('F j, Y \a\t g:i A') }}.
          </p>

          @if ($errors->any())
            <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size:13px;">
              <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
            </div>
          @endif

          <form method="POST" action="{{ route('account-setup.complete') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label class="pw-field-label" for="setupPassword">New Password</label>
            <div class="pw-wrap">
              <input type="password" name="password" id="setupPassword"
                     class="pw-field @error('password') is-invalid @enderror"
                     placeholder="At least 8 characters" autocomplete="new-password"
                     required autofocus />
              <button type="button" class="pw-toggle"
                      onclick="togglePw('setupPassword','setupPasswordIcon')"
                      aria-label="Toggle password visibility">
                <i class="bi bi-eye" id="setupPasswordIcon"></i>
              </button>
            </div>

            <label class="pw-field-label" for="setupPasswordConfirmation">Confirm Password</label>
            <div class="pw-wrap">
              <input type="password" name="password_confirmation" id="setupPasswordConfirmation"
                     class="pw-field" placeholder="Re-enter your password"
                     autocomplete="new-password" required />
              <button type="button" class="pw-toggle"
                      onclick="togglePw('setupPasswordConfirmation','setupPasswordConfirmationIcon')"
                      aria-label="Toggle password visibility">
                <i class="bi bi-eye" id="setupPasswordConfirmationIcon"></i>
              </button>
            </div>

            <p class="pw-note">Minimum 8 characters with at least one letter and one number.</p>

            <button type="submit" class="btn-proceed">
              {{ $isStudent ? 'Continue Setup' : 'Activate Account' }}
            </button>
          </form>
        @endif
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
