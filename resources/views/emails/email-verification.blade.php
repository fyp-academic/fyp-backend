<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email – EduAI LMS</title>
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  .wrapper{max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:40px 40px 32px;text-align:center}
  .header img{width:48px;height:48px;margin-bottom:16px}
  .header h1{color:#fff;margin:0;font-size:22px;font-weight:700;letter-spacing:-.3px}
  .header p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px}
  .body{padding:40px}
  .greeting{font-size:18px;font-weight:600;color:#1e293b;margin:0 0 12px}
  .text{font-size:15px;color:#475569;line-height:1.7;margin:0 0 28px}
  .btn{display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:15px;font-weight:600;letter-spacing:.2px}
  .btn-wrap{text-align:center;margin:0 0 32px}
  .divider{border:none;border-top:1px solid #e2e8f0;margin:32px 0}
  .small{font-size:13px;color:#94a3b8;line-height:1.6}
  .url-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;font-size:12px;color:#64748b;word-break:break-all;margin:12px 0 0}
  .footer{background:#f8fafc;padding:24px 40px;text-align:center}
  .footer p{margin:0;font-size:12px;color:#94a3b8}
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>EduAI LMS</h1>
    <p>Intelligent Learning Management System</p>
  </div>
  <div class="body">
    <p class="greeting">Hello, {{ $userName }} 👋</p>
    <p class="text">
      Thank you for registering on <strong>EduAI LMS</strong>. To activate your account and start learning,
      please verify your email address by clicking the button below.
    </p>
    <div class="btn-wrap">
      <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
    </div>
    <p class="text">
      This link will expire in <strong>{{ $expiresIn }}</strong>. If you did not create an account, you can
      safely ignore this email.
    </p>
    <hr class="divider">
    <p class="small">If the button above doesn't work, copy and paste the URL below into your browser:</p>
    <div class="url-box">{{ $verificationUrl }}</div>
  </div>
  <div class="footer">
    <p>© {{ date('Y') }} EduAI LMS · All rights reserved</p>
    <p style="margin-top:4px">This is an automated message, please do not reply.</p>
  </div>
</div>
</body>
</html>
