<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Your Notification Digest' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0 0 10px 0;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .notification-list {
            margin: 20px 0;
        }
        .notification-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 15px 0;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-title {
            font-weight: 600;
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 5px;
        }
        .notification-body {
            color: #555;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .notification-meta {
            color: #999;
            font-size: 12px;
        }
        .notification-type {
            display: inline-block;
            background-color: #e0e7ff;
            color: #4338ca;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-transform: uppercase;
            margin-right: 8px;
        }
        .count-badge {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .footer {
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
        .view-all {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">{{ config('app.name') }}</h1>
            <p class="subtitle">Your {{ $frequency }} digest - {{ $count }} notification{{ $count > 1 ? 's' : '' }}</p>
        </div>
        
        <div class="notification-list">
            @foreach($notifications as $notification)
                <div class="notification-item">
                    <div class="notification-title">{{ $notification['title'] }}</div>
                    <div class="notification-body">{{ $notification['body'] }}</div>
                    <div class="notification-meta">
                        <span class="notification-type">{{ str_replace('_', ' ', $notification['type']) }}</span>
                        {{ $notification['created_at'] }}
                    </div>
                </div>
            @endforeach
        </div>
        
        <a href="{{ url('/notifications') }}" class="view-all">View All Notifications</a>
        
        <div class="footer">
            <p>
                You're receiving this {{ $frequency }} digest because of your notification settings.
                <br><br>
                To change your preferences, <a href="{{ url('/settings/notifications') }}">visit your settings</a>.
            </p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
