@php
    $announcementGroups = app(\App\Services\AnnouncementService::class)
        ->displayGroups($dashboardAnnouncements ?? collect());
@endphp
@if(in_array(auth()->user()?->role, \App\Models\Announcement::RECIPIENT_ROLES, true))
<section class="dashboard-announcements" aria-labelledby="dashboard-announcements-title">
    <div class="dashboard-announcements-head">
        <span class="dashboard-announcements-icon" aria-hidden="true">
            <i class="bi bi-megaphone-fill"></i>
        </span>
        <div>
            <h2 id="dashboard-announcements-title">Announcements</h2>
            <p>Important updates from the System Administrator.</p>
        </div>
        <a href="{{ route('announcements.index') }}" class="dashboard-announcements-view-all">View All</a>
    </div>

    <div class="dashboard-announcement-board">
        @forelse($announcementGroups as $group)
            <article class="dashboard-announcement-card">
                <div class="dashboard-announcement-date">
                    Changes as of {{ $group['date']->format('F j, Y') }}:
                </div>
                <ul class="dashboard-announcement-items" aria-label="Changes posted on {{ $group['date']->format('F j, Y') }}">
                    @foreach($group['messages'] as $item)
                        <li>
                            @if($item['personal'])
                                <span class="dashboard-announcement-badge">Personal</span>
                            @endif
                            {{ $item['message'] }}
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <div class="dashboard-announcement-empty">No announcements yet.</div>
        @endforelse
    </div>
</section>

@once
    <style>
        .dashboard-announcements {
            overflow: hidden;
            margin-bottom: 20px;
            border: 1px solid #dce3ee;
            border-radius: 12px;
            background: #fff;
        }
        .dashboard-announcements-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 18px;
            background: #3156C7;
            color: #fff;
        }
        .dashboard-announcements-head > div {
            flex: 1 1 auto;
            min-width: 0;
        }
        .dashboard-announcements-view-all {
            flex: 0 0 auto;
            padding: 6px 12px;
            border: 1px solid rgba(255, 255, 255, .4);
            border-radius: 999px;
            color: #fff;
            font-size: .72rem;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }
        .dashboard-announcements-view-all:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }
        .dashboard-announcements-icon {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 8px;
            background: rgba(255, 255, 255, .12);
            font-size: .92rem;
        }
        .dashboard-announcements-head h2 {
            margin: 0;
            color: #fff;
            font-size: .98rem;
            font-weight: 750;
        }
        .dashboard-announcements-head p {
            margin: 3px 0 0;
            color: rgba(255, 255, 255, .8);
            font-size: .73rem;
        }
        .dashboard-announcement-board {
            background: #fff;
        }
        .dashboard-announcement-card {
            min-width: 0;
            padding: 17px 20px 18px;
            background: #fff;
        }
        .dashboard-announcement-card + .dashboard-announcement-card {
            border-top: 1px solid #e8edf4;
        }
        .dashboard-announcement-date {
            margin-bottom: 12px;
            color: #203c91;
            font-size: .76rem;
            font-weight: 750;
        }
        .dashboard-announcement-items {
            display: grid;
            gap: 0;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .dashboard-announcement-items li {
            position: relative;
            padding: 9px 0 9px 20px;
            color: #526078;
            font-size: .77rem;
            line-height: 1.6;
        }
        .dashboard-announcement-items li::before {
            content: '';
            position: absolute;
            top: 17px;
            left: 2px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #3156C7;
        }
        .dashboard-announcement-items li + li {
            border-top: 1px solid #edf1f7;
        }
        .dashboard-announcement-badge {
            display: inline-block;
            margin-right: 6px;
            padding: 1px 7px;
            border-radius: 999px;
            background: #edf3ff;
            color: #3156C7;
            font-size: .64rem;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: .03em;
            vertical-align: middle;
        }
        .dashboard-announcement-empty {
            padding: 20px;
            color: #64748b;
            font-size: .8rem;
            text-align: center;
        }
        @media (max-width: 575.98px) {
            .dashboard-announcements-head { align-items: flex-start; }
            .dashboard-announcement-card { padding: 15px 16px 16px; }
        }
    </style>
@endonce
@endif
