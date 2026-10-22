@extends('layouts.student')
@section('title', $complaint->report_number)
@section('content')
@php
    $statusLabels = [
        'pending_review' => ['label' => 'Pending Review', 'class' => 'prism-badge-orange'],
        'under_review' => ['label' => 'Under Review', 'class' => 'prism-badge-blue'],
        'converted_to_case' => ['label' => 'Processed', 'class' => 'prism-badge-green'],
        'dismissed' => ['label' => 'Closed', 'class' => 'prism-badge-gray'],
    ];
    $status = $statusLabels[$complaint->status] ?? ['label' => 'Closed', 'class' => 'prism-badge-gray'];
@endphp

<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'My Complaints', 'url' => route('student.complaints.index')],
            ['label' => $complaint->report_number],
        ]" />
        <h1 class="prism-page-title">{{ $complaint->report_number }}</h1>
        <p class="prism-page-sub">Complaint submission receipt</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Complaint Details</h2>
                </div>
                <div class="prism-card-body">
                    <table class="prism-detail-table">
                        <tr>
                            <th>Report No.</th>
                            <td class="prism-ref-number">{{ $complaint->report_number }}</td>
                        </tr>
                        <tr>
                            <th>Current Status</th>
                            <td><span class="prism-badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                        </tr>
                        <tr>
                            <th>Incident Date and Time</th>
                            <td>
                                {{ $complaint->date_of_incident->format('F d, Y') }}
                                @if($complaint->time_of_incident)
                                    at {{ \Carbon\Carbon::parse($complaint->time_of_incident)->format('g:i A') }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Location</th>
                            <td>{{ $complaint->location ?: 'Not provided' }}</td>
                        </tr>
                    </table>

                    <hr class="prism-divider">

                    <p class="prism-label mb-2">Description</p>
                    <div style="font-size:0.875rem;color:var(--text-primary);line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">{{ $complaint->description }}</div>
                </div>
            </div>

            <div class="prism-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Attachments</h2>
                </div>
                <div class="prism-card-body">
                    @if($complaint->attachments->isEmpty())
                        <div class="text-muted-cell" style="font-size:0.88rem;">No attachments submitted.</div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($complaint->attachments as $attachment)
                                @php
                                    $iconMap = [
                                        'pdf' => 'bi-file-earmark-pdf text-danger',
                                        'jpg' => 'bi-file-earmark-image text-primary',
                                        'jpeg' => 'bi-file-earmark-image text-primary',
                                        'png' => 'bi-file-earmark-image text-primary',
                                    ];
                                    $icon = $iconMap[$attachment->file_type] ?? 'bi-file-earmark';
                                @endphp
                                <div class="d-flex align-items-center justify-content-between" style="padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi {{ $icon }}" style="font-size:1.1rem;"></i>
                                        <div>
                                            <div style="font-size:0.84rem;font-weight:600;">{{ $attachment->file_name }}</div>
                                            <div style="font-size:0.75rem;color:var(--text-muted);">
                                                {{ strtoupper($attachment->file_type) }} &middot;
                                                {{ round($attachment->file_size_bytes / 1024, 1) }} KB
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button"
                                       class="prism-btn prism-btn-outline"
                                       style="padding:6px 10px;font-size:0.76rem;"
                                       data-attachment-preview
                                       data-attachment-url="{{ route('student.complaints.attachments.view', [$complaint, $attachment]) }}"
                                       data-attachment-name="{{ $attachment->file_name }}"
                                       data-attachment-type="{{ $attachment->file_type }}">
                                        View
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.attachment-preview-modal')
@endsection
