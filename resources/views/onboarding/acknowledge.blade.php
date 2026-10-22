@extends('layouts.app')

@section('title', 'Confirm Your Information')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/verify-otp.css') }}?v={{ filemtime(public_path('css/verify-otp.css')) }}" />
  <style>
    .step-track { display:flex; align-items:center; justify-content:center; gap:0; margin-bottom:20px; }
    .step-node { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; border:2px solid rgba(255,255,255,0.25); color:rgba(255,255,255,0.45); background:transparent; }
    .step-node.active { border-color:#93C5FD; background:rgba(147,197,253,0.15); color:#fff; }
    .step-node.done { border-color:#4ADE80; background:rgba(74,222,128,0.15); color:#4ADE80; }
    .step-line { width:40px; height:2px; background:rgba(255,255,255,0.15); }
    .step-line.done { background:#4ADE80; }

    .summary-box {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 10px;
      padding: 14px 16px;
      margin-bottom: 14px;
      font-size: 13px;
    }
    .summary-row { display:flex; padding:5px 0; border-bottom:1px solid rgba(255,255,255,0.08); }
    .summary-row:last-child { border:none; }
    .summary-key { color:#94A3B8; font-weight:600; width:130px; flex-shrink:0; }
    .summary-val { color:#E2E8F0; }

    .policy-box {
      background: rgba(234,179,8,0.1);
      border: 1px solid rgba(234,179,8,0.3);
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 16px;
      font-size: 12px;
      color: #FDE68A;
      line-height: 1.6;
    }
    .ack-check { display:flex; align-items:flex-start; gap:10px; margin-bottom:18px; cursor:pointer; }
    .ack-check input[type="checkbox"] { width:17px; height:17px; margin-top:2px; flex-shrink:0; accent-color:#2563EB; cursor:pointer; }
    .ack-check label { color:#E2E8F0; font-size:13px; line-height:1.5; cursor:pointer; }

    .btn-proceed { width:100%; height:46px; border:none; border-radius:10px; background:#2563EB; color:#fff; font-family:'Poppins',sans-serif; font-size:15px; font-weight:600; cursor:pointer; transition:background 0.2s; }
    .btn-proceed:hover { background:#1D4ED8; }
    .btn-back { background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#E2E8F0; border-radius:10px; height:46px; padding:0 20px; font-family:'Poppins',sans-serif; font-size:14px; cursor:pointer; flex-shrink:0; }
    .btn-back:hover { background:rgba(255,255,255,0.18); }
  </style>
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

        <div class="step-track">
          <div class="step-node done"><i class="bi bi-check-lg" style="font-size:11px;"></i></div>
          <div class="step-line done"></div>
          <div class="step-node done"><i class="bi bi-check-lg" style="font-size:11px;"></i></div>
          <div class="step-line done"></div>
          <div class="step-node active">3</div>
        </div>

        <h1 class="form-heading">Confirm Your Information</h1>
        <p class="form-subtext mb-4">Please review the details below before completing setup.</p>

        @if ($errors->any())
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
          </div>
        @endif

        <div class="summary-box">
          <div class="summary-row">
            <span class="summary-key">Name</span>
            <span class="summary-val">{{ auth()->user()->full_name }}</span>
          </div>
          @if(auth()->user()->studentProfile)
            <div class="summary-row">
              <span class="summary-key">Student No.</span>
              <span class="summary-val prism-ref-number">{{ auth()->user()->studentProfile->student_number }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Program</span>
              <span class="summary-val">{{ $profile->program }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Year Level</span>
              <span class="summary-val">{{ $profile->year_level }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Contact</span>
              <span class="summary-val">{{ $profile->contact_number }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Guardian</span>
              <span class="summary-val">{{ $profile->guardian_name }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Guardian Contact</span>
              <span class="summary-val">{{ $profile->guardian_contact }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Relationship</span>
              <span class="summary-val">{{ $profile->guardian_relationship }}</span>
            </div>
          @endif
        </div>

        <div class="policy-box">
          <i class="bi bi-shield-exclamation me-1"></i>
          <strong>Data Accuracy Policy</strong><br>
          Falsification of data in this system may constitute a violation of the NU Clark Student Handbook
          and may result in appropriate disciplinary action.
        </div>

        <form method="POST" action="{{ route('onboarding.acknowledge.submit') }}">
          @csrf

          <div class="ack-check">
            <input type="checkbox" name="acknowledged" id="ackCheck" value="1" required />
            <label for="ackCheck">
              I confirm that all the information I have provided is true and correct.
              I understand that falsification of data may result in disciplinary action.
            </label>
          </div>

          <button type="submit" class="btn-proceed">
            Complete Setup
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
