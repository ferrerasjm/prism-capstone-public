@extends('layouts.app')

@section('title', 'Complete Your Profile')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/verify-otp.css') }}?v={{ filemtime(public_path('css/verify-otp.css')) }}" />
  <style>
    .step-track { display:flex; align-items:center; justify-content:center; gap:0; margin-bottom:20px; }
    .step-node { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; border:2px solid rgba(255,255,255,0.25); color:rgba(255,255,255,0.45); background:transparent; }
    .step-node.active { border-color:#93C5FD; background:rgba(147,197,253,0.15); color:#fff; }
    .step-node.done { border-color:#4ADE80; background:rgba(74,222,128,0.15); color:#4ADE80; }
    .step-line { width:40px; height:2px; background:rgba(255,255,255,0.15); }
    .step-line.done { background:#4ADE80; }

    .pf-label { display:block; color:#E2E8F0; font-size:13px; font-weight:600; margin-bottom:5px; }
    .pf-input, .pf-select {
      width:100%; height:42px;
      border-radius:10px; border:1px solid rgba(255,255,255,0.25);
      background:rgba(255,255,255,0.1); color:#fff;
      padding:0 12px; font-family:'Poppins',sans-serif; font-size:13px;
      outline:none; margin-bottom:10px;
    }
    .pf-input::placeholder { color:#C7D2FE; }
    .pf-input:focus, .pf-select:focus { border-color:#93C5FD; box-shadow:0 0 0 3px rgba(59,130,246,0.25); }
    .pf-select option { background:#1B2A6B; color:#fff; }
    .pf-select optgroup { background:#14295f; color:#93C5FD; font-weight:700; }
    .pf-divider { border-color:rgba(255,255,255,0.15); margin:14px 0 12px; }
    .pf-section-label { color:#93C5FD; font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; margin-bottom:10px; }
    .btn-proceed { width:100%; height:46px; border:none; border-radius:10px; background:#2563EB; color:#fff; font-family:'Poppins',sans-serif; font-size:15px; font-weight:600; cursor:pointer; transition:background 0.2s; }
    .btn-proceed:hover { background:#1D4ED8; }
    .btn-back { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#E2E8F0; border-radius:10px; height:46px; padding:0 20px; font-family:'Poppins',sans-serif; font-size:14px; cursor:pointer; transition:background 0.15s; }
    .btn-back:hover { background:rgba(255,255,255,0.18); color:#fff; }
  </style>
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="d-flex flex-column align-items-center justify-content-center min-vh-100 px-3 py-4 content-wrap auth-centered-wrap">
    <div class="glass-card w-100" style="max-width: 650px;">

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
          <div class="step-node active">2</div>
          <div class="step-line"></div>
          <div class="step-node">3</div>
        </div>

        <h1 class="form-heading">Complete Your Profile</h1>
        <p class="form-subtext mb-4">
          This information is used by the SDO for case processing.<br>
          Please ensure all details are accurate.
        </p>

        @if ($errors->any())
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('onboarding.profile.update') }}">
          @csrf

          <label class="pf-label">Program / Course</label>
          <select id="pf-program" name="program" class="pf-select @error('program') is-invalid @enderror" required>
            <option value="" disabled {{ old('program', auth()->user()->studentProfile?->program) ? '' : 'selected' }}>Select program / course</option>

            @foreach($academicUnits as $unit)
              <optgroup label="{{ strtoupper($unit->name) }}">
                @foreach($unit->programs as $program)
                  <option value="{{ $program->name }}" data-shs="{{ $unit->name === $shsUnitName ? '1' : '0' }}" {{ old('program', auth()->user()->studentProfile?->program) === $program->name ? 'selected' : '' }}>{{ $program->name }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select>

          <label class="pf-label">Year Level</label>
          <select id="pf-year-level" name="year_level" class="pf-select @error('year_level') is-invalid @enderror" required>
            <option value="" disabled {{ !old('year_level') ? 'selected' : '' }}>Select year level</option>
            @foreach($collegeYearLevels as $yr)
              <option value="{{ $yr }}" data-group="college" {{ old('year_level') === $yr ? 'selected' : '' }}>{{ $yr }}</option>
            @endforeach
            @foreach($shsYearLevels as $yr)
              <option value="{{ $yr }}" data-group="shs" {{ old('year_level') === $yr ? 'selected' : '' }}>{{ $yr }}</option>
            @endforeach
          </select>

          <label class="pf-label">Contact Number</label>
          <input type="text" name="contact_number" class="pf-input @error('contact_number') is-invalid @enderror"
            placeholder="09XXXXXXXXX"
            value="{{ old('contact_number', auth()->user()->studentProfile?->contact_number) }}" required />

          <hr class="pf-divider" />
          <p class="pf-section-label"><i class="bi bi-person-lines-fill me-1"></i> Guardian / Emergency Contact</p>

          <label class="pf-label">Full Name</label>
          <input type="text" name="guardian_name" class="pf-input @error('guardian_name') is-invalid @enderror"
            placeholder="Guardian's full name"
            value="{{ old('guardian_name', auth()->user()->studentProfile?->guardian_name) }}" required />

          <div class="row g-2">
            <div class="col-7">
              <label class="pf-label">Contact Number</label>
              <input type="text" name="guardian_contact" class="pf-input @error('guardian_contact') is-invalid @enderror"
                placeholder="09XXXXXXXXX"
                value="{{ old('guardian_contact', auth()->user()->studentProfile?->guardian_contact) }}" required />
            </div>
            <div class="col-5">
              <label class="pf-label">Relationship</label>
              <select name="guardian_relationship" class="pf-select @error('guardian_relationship') is-invalid @enderror" required>
                <option value="" disabled selected>Select</option>
                @foreach(['Father','Mother','Guardian','Sibling','Other'] as $rel)
                  <option value="{{ $rel }}" {{ old('guardian_relationship') === $rel ? 'selected' : '' }}>{{ $rel }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <button type="submit" class="btn-proceed">
            Continue
          </button>

        </form>
      </div>
    </div>

    <script>
      (function () {
        var programSelect = document.getElementById('pf-program');
        var yearLevelSelect = document.getElementById('pf-year-level');

        function syncYearLevelOptions() {
          var selectedProgramOption = programSelect.options[programSelect.selectedIndex];
          var isShs = selectedProgramOption ? selectedProgramOption.dataset.shs === '1' : false;
          var currentValue = yearLevelSelect.value;
          var stillValid = false;

          Array.prototype.forEach.call(yearLevelSelect.options, function (option) {
            if (!option.dataset.group) {
              return;
            }

            var matches = isShs ? option.dataset.group === 'shs' : option.dataset.group === 'college';
            option.hidden = !matches;
            option.disabled = !matches;

            if (matches && option.value === currentValue) {
              stillValid = true;
            }
          });

          if (!stillValid) {
            yearLevelSelect.value = '';
          }
        }

        programSelect.addEventListener('change', syncYearLevelOptions);
        syncYearLevelOptions();
      })();
    </script>


    <p class="text-white mt-4 text-center fst-italic fw-semibold disclaimer">
      *This system contains confidential student disciplinary records. Unauthorized access is strictly prohibited.
    </p>
  </div>
</div>
@endsection
