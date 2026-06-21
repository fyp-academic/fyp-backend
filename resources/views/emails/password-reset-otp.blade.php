<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Password Reset Code</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #ece7df;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        .serif {
            font-family: "Fraunces", Georgia, 'Times New Roman', serif;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            background: #f6f3ee;
            border: 1px solid #dad3c8;
            border-radius: 16px;
            overflow: hidden;
        }
        .header {
            background: #16140f;
            padding: 36px 40px;
            text-align: center;
        }
        .header .brand {
            color: #f6f3ee;
            font-size: 30px;
            font-weight: 500;
            margin: 0;
            letter-spacing: 0.5px;
            line-height: 1;
        }
        .header .tagline {
            color: #b8b1a4;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            margin: 12px 0 0;
        }
        .body {
            padding: 40px;
            color: #6b655c;
            font-size: 16px;
            line-height: 1.7;
        }
        .greeting {
            font-size: 22px;
            font-weight: 500;
            color: #16140f;
            margin: 0 0 16px;
        }
        .body p {
            margin: 0 0 16px;
        }
        .body strong {
            color: #16140f;
        }
        .code-box {
            background: #ece7df;
            border: 1px solid #dad3c8;
            border-top: 3px solid #b5613d;
            border-radius: 12px;
            padding: 24px 32px;
            margin: 28px 0;
            text-align: center;
        }
        .code-label {
            font-size: 12px;
            color: #8c4a2f;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
        }
        .code-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', Consolas, monospace;
            font-size: 38px;
            font-weight: 700;
            color: #16140f;
            letter-spacing: 8px;
            margin: 0;
        }
        .expiry {
            background: #f6f3ee;
            border: 1px solid #dad3c8;
            border-radius: 10px;
            padding: 16px 20px;
            font-size: 14px;
            color: #6b655c;
            margin-top: 24px;
        }
        .expiry strong {
            color: #16140f;
        }
        .warning {
            background: #ece7df;
            border-left: 4px solid #8c4a2f;
            border-radius: 0 10px 10px 0;
            padding: 16px 20px;
            margin-top: 20px;
            font-size: 14px;
            color: #6b655c;
        }
        .warning strong {
            color: #8c4a2f;
        }
        .footer {
            padding: 24px 40px;
            background: #ece7df;
            border-top: 1px solid #dad3c8;
            font-size: 13px;
            color: #6b655c;
            text-align: center;
            line-height: 1.7;
        }
        .footer a {
            color: #b5613d;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .wrapper {
                margin: 0;
                border-radius: 0;
                border-left: 0;
                border-right: 0;
            }
            .body {
                padding: 28px 24px;
            }
            .code-value {
                font-size: 30px;
                letter-spacing: 5px;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ece7df;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" class="wrapper">
                    <tr>
                        <td class="header">
                            <p class="brand serif">APES</p>
                            <p class="tagline">AI Personalization eLearning</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="body">
                            <p class="greeting serif">Hello, {{ $userName }}</p>
                            <p>We received a request to reset your password for your <strong>APES LMS</strong> account. Use the verification code below to complete the password reset process.</p>

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
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <p style="margin:0 0 6px;">Need help? Contact us at <a href="mailto:codagenz10@gmail.com">codagenz10@gmail.com</a></p>
                            <p style="margin:0;">APES LMS &middot; Tanzania</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
