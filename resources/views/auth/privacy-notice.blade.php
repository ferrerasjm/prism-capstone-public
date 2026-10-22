@extends('layouts.app')

@section('title', 'PRISM Privacy Notice')

@section('body-class', 'privacy-notice-page')

@section('css')
<link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}" />
<link rel="stylesheet" href="{{ asset('css/privacy-notice.css') }}?v={{ filemtime(public_path('css/privacy-notice.css')) }}" />
@endsection

@section('content')
<div class="page-bg">
  <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="vec-left" aria-hidden="true" />
  <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="vec-right" aria-hidden="true" />

  <div class="privacy-overlay">
    @include('auth.partials.privacy-modal-card')
  </div>
</div>
@endsection

@section('scripts')
@include('auth.partials.privacy-modal-script')
@endsection
