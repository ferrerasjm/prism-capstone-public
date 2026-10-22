@extends($layout)
@section('title', 'Notifications')
@section('css')
<style>
    .account-notifications-panel { overflow: hidden; border-radius: 12px; }
    .account-notifications-list { display: flex; flex-direction: column; }
    .account-notification-row {
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
    .account-notification-row:first-child { border-top: 0; }
    .account-notification-row.unread { background: #EFF6FF; }
    .account-notification-row:hover { background: #F8FAFC; }
    .account-notification-row-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .86rem;
        font-weight: 700;
        color: #172554;
    }
    .notif-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; flex: 0 0 auto; }
    .account-notification-row-message {
        display: block;
        margin-top: 3px;
        font-size: .8rem;
        line-height: 1.5;
        color: #64748B;
    }
    .account-notification-row-time {
        display: block;
        margin-top: 5px;
        font-size: .72rem;
        color: #94A3B8;
    }
    .account-notifications-pagination {
        padding: 12px 18px;
        border-top: 1px solid #F0F2F8;
        background: #fff;
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
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="prism-btn prism-btn-outline">Mark All as Read</button>
            </form>
        @endif
    </div>

    <div class="prism-card account-notifications-panel">
        <div class="prism-card-header"><h2 class="prism-card-title">All Notifications</h2></div>
        @if($notifications->isEmpty())
            <div class="prism-table-empty">You have no notifications yet.</div>
        @else
            <div class="account-notifications-list">
                @foreach($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.open', $notification) }}">
                        @csrf
                        <button type="submit" class="account-notification-row {{ $notification->read_at ? '' : 'unread' }}">
                            <span class="account-notification-row-title">
                                @unless($notification->read_at)
                                    <span class="notif-dot"></span>
                                @endunless
                                {{ $notification->data['title'] ?? 'Notification' }}
                            </span>
                            <span class="account-notification-row-message">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="account-notification-row-time">{{ $notification->created_at->diffForHumans() }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
            <div class="account-notifications-pagination prism-pagination"><x-pagination :paginator="$notifications" /></div>
        @endif
    </div>
</div>
@endsection
