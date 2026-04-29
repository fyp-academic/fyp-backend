<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Notification' }}</title>
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
        .body {
            font-size: 16px;
            color: #444;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .footer {
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
        .unsubscribe {
            color: #666;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">{{ config('app.name') }}</h1>
        </div>
        
        <div class="body">
            <h2>{{ $title }}</h2>
            <p>{{ $body }}</p>
            
            @if(isset($action_url) && $action_url)
                <a href="{{ $action_url }}" class="button">{{ $action_text ?? 'View Details' }}</a>
            @endif
        </div>
        
        <div class="footer">
            <p>
                You received this email because you have notifications enabled for {{ config('app.name') }}.
                <br><br>
                <a href="{{ $unsubscribe_url ?? '#' }}" class="unsubscribe">Unsubscribe from these notifications</a>
            </p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
