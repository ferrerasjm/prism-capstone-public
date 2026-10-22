@extends('layouts.student')
@section('title', 'Notifications')
@section('css')
<style>
    .student-notifications-panel { overflow: hidden; border-radius: 12px; }
    .student-notifications-list { display: flex; flex-direction: column; }
    .student-notification-row {
        display: block;
        width: 100%;
        padding: 14px 18px;
        border: 0;
        border-top: 1px solid #F0F2F8;
        background: #fff;
        text-align: left;
        font: inherit;
        cursor: pointer;
    }
    .student-notification-row:first-child { border-top: 0; }
    .student-notification-row.unread { background: #EFF6FF; }
    .student-notification-row:hover { background: #F8FAFC; }
    .student-notification-row-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .86rem;
        font-weight: 700;
        color: #172554;
    }
    .notif-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; flex: 0 0 auto; }
    .student-notification-row-message {
        display: block;
        margin-top: 3px;
        font-size: .8rem;
        line-height: 1.5;
        color: #64748B;
    }
    .student-notification-row-time {
        display: block;
        margin-top: 5px;
        font-size: .72rem;
        color: #94A3B8;
    }
    .student-notification-badge {
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #B45309;
        background: #FFF7ED;
        border: 1px solid #FED7AA;
        border-radius: 999px;
        padding: 2px 5px;
    }
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header d-flex align-items-start justify-content-between">
        <div>
            <h1 class="prism-page-title">Notifications</h1>
            <p class="prism-page-sub">All updates sent to your account</p>
        </div>
        @if($notifications->getCollection()->contains(fn ($n) => $n->read_at === null))
            <form method="POST" action="{{ route('student.notifications.read-all') }}">
                @csrf
                <button type="submit" class="prism-btn prism-btn-outline">Mark All as Read</button>
            </form>
        @endif
    </div>

    <div class="prism-card student-notifications-panel">
        <div class="prism-card-header"><h2 class="prism-card-title">All Notifications</h2></div>
        @if($notifications->isEmpty())
            <div class="prism-table-empty">You have no notifications yet.</div>
        @else
            <div class="student-notifications-list">
                @php $urgentNotificationKinds = ['nte', 'nte_reminder']; @endphp
                @foreach($notifications as $notification)
                    @php $isUrgentNotification = in_array($notification->data['kind'] ?? null, $urgentNotificationKinds, true); @endphp
                    <form method="POST" action="{{ route('student.notifications.open', $notification) }}">
                        @csrf
                        <button type="submit" class="student-notification-row {{ $notification->read_at ? '' : 'unread' }}">
                            <span class="student-notification-row-title">
                                @unless($notification->read_at)
                                    <span class="notif-dot"></span>
                                @endunless
                                {{ $notification->data['title'] ?? 'Notification' }}
                                @if($isUrgentNotification && ! $notification->read_at)
                                    <span class="student-notification-badge">Action Required</span>
                                @endif
                            </span>
                            <span class="student-notification-row-message">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="student-notification-row-time">{{ $notification->created_at->diffForHumans() }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
            <div class="student-pagination"><x-pagination :paginator="$notifications" /></div>
        @endif
    </div>
</div>
@endsection
