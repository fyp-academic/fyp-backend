<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Verify your email – APES UDOM</title>
<style>
  body{margin:0;padding:0;background:linear-gradient(135deg,#f1f5f9,#e0e7ff);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;min-height:100vh}
  .container{padding:40px 20px}
  .wrapper{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.12)}
  .header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:48px 40px 40px;text-align:center;position:relative}
  .header::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#22c55e,#4f46e5,#7c3aed)}
  .logo{width:64px;height:64px;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid rgba(255,255,255,.3)}
  .logo svg{width:36px;height:36px}
  .header h1{color:#fff;margin:0;font-size:26px;font-weight:800;letter-spacing:-.5px;text-transform:uppercase}
  .header p{color:rgba(255,255,255,.85);margin:8px 0 0;font-size:14px;font-weight:500;letter-spacing:.3px}
  .body{padding:48px 40px}
  .greeting{font-size:22px;font-weight:700;color:#1e293b;margin:0 0 20px;display:flex;align-items:center;gap:10px}
  .text{font-size:16px;color:#475569;line-height:1.8;margin:0 0 28px}
  .text strong{color:#4f46e5;font-weight:600}
  .highlight-box{background:linear-gradient(135deg,#fef3c7,#fde68a);border-left:4px solid #f59e0b;padding:16px 20px;border-radius:0 12px 12px 0;margin:24px 0}
  .highlight-box p{margin:0;font-size:14px;color:#92400e;font-weight:500}
  .btn{display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;padding:16px 40px;border-radius:12px;font-size:16px;font-weight:700;letter-spacing:.5px;box-shadow:0 4px 16px rgba(79,70,229,.35);transition:transform .2s,box-shadow .2s}
  .btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(79,70,229,.45)}
  .btn-wrap{text-align:center;margin:32px 0}
  .divider{border:none;height:1px;background:linear-gradient(90deg,transparent,#e2e8f0,transparent);margin:36px 0}
  .small{font-size:13px;color:#64748b;line-height:1.7;margin:0 0 12px}
  .url-box{background:#f8fafc;border:2px dashed #cbd5e1;border-radius:10px;padding:16px;font-size:13px;color:#475569;word-break:break-all;font-family:'SF Mono',Monaco,monospace;line-height:1.6}
  .footer{background:linear-gradient(180deg,#f8fafc,#f1f5f9);padding:32px 40px;text-align:center;border-top:1px solid #e2e8f0}
  .footer-brand{font-size:15px;font-weight:700;color:#1e293b;margin:0 0 8px;display:flex;align-items:center;justify-content:center;gap:8px}
  .footer-tagline{font-size:13px;color:#64748b;margin:0}
  .footer-divider{width:40px;height:3px;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:2px;margin:16px auto}
  .footer-legal{font-size:12px;color:#94a3b8;margin:12px 0 0}
  @media(max-width:480px){.container{padding:20px 16px}.header{padding:36px 24px 28px}.body{padding:32px 24px}.btn{padding:14px 28px;font-size:15px}}
</style>
</head>
<body>
<div class="container">
  <div class="wrapper">
    <div class="header">
      <div class="logo">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M2 17L12 22L22 17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M2 12L12 17L22 12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h1>APES UDOM</h1>
      <p>AI Powered E-Learning System</p>
    </div>
    <div class="body">
      <p class="greeting">Hello, {{ $userName }} <span style="font-size:28px">👋</span></p>
      <p class="text">
        Welcome to <strong>APES UDOM</strong>! Thank you for registering on our platform. To activate your account and unlock full access to all learning resources, please verify your email address by clicking the button below.
      </p>
      <div class="btn-wrap">
        <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
      </div>
      <div class="highlight-box">
        <p>⏰ This verification link will expire in <strong>{{ $expiresIn }}</strong>. Please verify promptly to secure your account.</p>
      </div>
      <p class="text" style="font-size:15px;color:#64748b">
        If you did not create this account, you can safely ignore this email. No further action is required.
      </p>
      <hr class="divider">
      <p class="small">Having trouble with the button? Copy and paste this URL into your browser:</p>
      <div class="url-box">{{ $verificationUrl }}</div>
    </div>
    <div class="footer">
      <p class="footer-brand">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M2 17L12 22L22 17" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        APES UDOM
      </p>
      <p class="footer-tagline">AI Powered E-Learning System · University of Dodoma</p>
      <div class="footer-divider"></div>
      <p class="footer-legal">© {{ date('Y') }} APES UDOM. All rights reserved.</p>
      <p class="footer-legal">This is an automated message. Please do not reply to this email.</p>
    </div>
  </div>
</div>
</body>
</html>
