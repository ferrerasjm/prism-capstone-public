@extends('layouts.app')

@section('title', 'Redirecting')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/redirecting.css') }}?v={{ filemtime(public_path('css/redirecting.css')) }}" />
@endsection

@section('content')
<div class="page-bg">
  <div class="splash-content">
    <p class="splash-title">National University Clark</p>
    <p class="splash-subtitle">Disciplinary Case Management System</p>
    <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="splash-logo" />
    <p class="splash-redirecting">Redirecting...</p>
  </div>
</div>

<div class="progress-bar-wrapper">
  <div class="progress-bar-fill"></div>
</div>
@endsection

@section('scripts')
<script>
  setTimeout(function () {
    window.location.href = @json($redirectTo ?? route('login'));
  }, 3000);
</script>
@endsection
