@extends($layout)
@section('title', 'Announcements')
@section('css')
<style>
    .announcements-panel { overflow: hidden; border-radius: 12px; }
    .announcement-card {
        padding: 20px 24px 22px;
        background: #fff;
    }
    .announcement-card + .announcement-card {
        border-top: 1px solid #e8edf4;
    }
    .announcement-date {
        margin-bottom: 12px;
        color: #203c91;
        font-size: .8rem;
        font-weight: 750;
    }
    .announcement-items {
        display: grid;
        gap: 0;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .announcement-items li {
        position: relative;
        padding: 9px 0 9px 20px;
        color: #526078;
        font-size: .82rem;
        line-height: 1.6;
    }
    .announcement-time {
        margin-left: 6px;
        color: #94a3b8;
        font-size: .72rem;
        white-space: nowrap;
    }
    .announcement-items li::before {
        content: '';
        position: absolute;
        top: 17px;
        left: 2px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #3156C7;
    }
    .announcement-items li + li {
        border-top: 1px solid #edf1f7;
    }
    .announcements-list {
        max-height: 560px;
        overflow-y: auto;
    }
    .announcements-pagination {
        padding: 12px clamp(16px, 1.4vw, 22px);
        border-top: 1px solid #e8edf4;
        background: #fbfdff;
    }
    .announcement-badge {
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
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <div>
            <h1 class="prism-page-title">Announcements</h1>
            <p class="prism-page-sub">Important updates from the System Administrator.</p>
        </div>
    </div>

    <div class="prism-card announcements-panel">
        <div class="prism-card-header"><h2 class="prism-card-title">All Announcements</h2></div>
        @if($announcementGroups->isEmpty())
            <div class="prism-table-empty">No announcements yet.</div>
        @else
            <div class="announcements-list">
                @foreach($announcementGroups as $group)
                    <article class="announcement-card">
                        <div class="announcement-date">
                            Changes as of {{ $group['date']->format('F j, Y') }}:
                        </div>
                        <ul class="announcement-items" aria-label="Changes posted on {{ $group['date']->format('F j, Y') }}">
                            @foreach($group['messages'] as $item)
                                <li>
                                    @if($item['personal'])
                                        <span class="announcement-badge">Personal</span>
                                    @endif
                                    {{ $item['message'] }}
                                    <span class="announcement-time">{{ $item['time'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
            <div class="announcements-pagination prism-pagination"><x-pagination :paginator="$announcements" /></div>
        @endif
    </div>
</div>
@endsection
