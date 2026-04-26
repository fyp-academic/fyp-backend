<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .header p {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            margin: 8px 0 0;
        }
        .body {
            padding: 40px;
            color: #334155;
            font-size: 16px;
            line-height: 1.7;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
        }
        .code-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b;
            border-radius: 0 12px 12px 0;
            padding: 24px 32px;
            margin: 24px 0;
            text-align: center;
        }
        .code-label {
            font-size: 13px;
            color: #92400e;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .code-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 36px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 6px;
            margin: 0;
        }
        .expiry {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px 20px;
            font-size: 14px;
            color: #64748b;
            margin-top: 24px;
        }
        .expiry strong {
            color: #475569;
        }
        .warning {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            border-radius: 0 8px 8px 0;
            padding: 16px 20px;
            margin-top: 20px;
            font-size: 14px;
            color: #991b1b;
        }
        .footer {
            padding: 24px 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #94a3b8;
            text-align: center;
            line-height: 1.6;
        }
        .footer a {
            color: #4f46e5;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .wrapper {
                margin: 0;
                border-radius: 0;
            }
            .body {
                padding: 24px;
            }
            .code-value {
                font-size: 28px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>APES UDOM</h1>
            <p>AI Powered E-Learning System</p>
        </div>
        <div class="body">
            <p class="greeting">Hello, {{ $userName }}</p>
            <p>We received a request to reset your password for your <strong>APES UDOM</strong> account. Use the verification code below to complete the password reset process.</p>

            <div class="code-box">
                <div class="code-label">Your Password Reset Code</div>
                <p class="code-value">{{ $code }}</p>
            </div>

            <div class="expiry">
                This code will expire in <strong>{{ $expiresInMinutes }} minutes</strong>. Please enter it promptly to reset your password.
            </div>

            <div class="warning">
                <strong>Didn't request this?</strong> If you didn't request a password reset, please ignore this email. Your password will remain secure and unchanged.
            </div>
        </div>
        <div class="footer">
            <p>Need help? Contact us at <a href="mailto:support@codagenz.com">support@codagenz.com</a></p>
            <p>University of Dodoma &middot; Tanzania</p>
        </div>
    </div>
</body>
</html>
