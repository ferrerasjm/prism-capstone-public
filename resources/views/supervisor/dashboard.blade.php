@extends('layouts.staff')
@section('title', 'Supervisor Dashboard')
@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <h1 class="prism-page-title">Supervisor Dashboard</h1>
        <p class="prism-page-sub">Manage your University Service students</p>
    </div>

    @include('partials.dashboard-announcements')

    <div class="d-flex justify-content-center mt-4">
        <a href="{{ route('supervisor.us.index') }}"
           class="prism-btn prism-btn-primary">
            <i class="bi bi-person-workspace"></i> View My Assignments
        </a>
    </div>
</div>
@endsection
