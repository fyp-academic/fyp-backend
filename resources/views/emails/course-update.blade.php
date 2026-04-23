<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Update – APES UDOM</title>
<style>
  body{margin:0;padding:0;background:linear-gradient(135deg,#f1f5f9,#e0e7ff);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh}
  .container{padding:40px 20px}
  .wrapper{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.12)}
  .header{padding:32px 40px;text-align:center;border-bottom:1px solid #e2e8f0}
  .header-top{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:20px}
  .logo{width:40px;height:40px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:10px;display:flex;align-items:center;justify-content:center}
  .logo svg{width:24px;height:24px}
  .brand{font-size:18px;font-weight:800;color:#1e293b;letter-spacing:-.3px}
  .badge{display:inline-block;padding:6px 16px;border-radius:999px;font-size:12px;font-weight:600;margin-bottom:16px;text-transform:uppercase;letter-spacing:.5px}
  .header h1{margin:0;font-size:24px;font-weight:700;letter-spacing:-.3px}
  .header p{margin:8px 0 0;font-size:14px;color:#64748b}
  .body{padding:32px 40px 40px}
  .card{border-radius:16px;padding:28px;margin-bottom:24px}
  .card-title{font-size:18px;font-weight:700;margin:0 0 12px}
  .card-desc{font-size:15px;line-height:1.7;margin:0 0 8px;color:#475569}
  .meta{font-size:13px;margin:12px 0 0;color:#64748b}
  .btn{display:inline-block;color:#fff;text-decoration:none;padding:14px 32px;border-radius:12px;font-size:15px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.15)}
  .btn-wrap{text-align:center;margin:28px 0 0}
  .divider{border:none;height:1px;background:linear-gradient(90deg,transparent,#e2e8f0,transparent);margin:32px 0}
  .footer{background:linear-gradient(180deg,#f8fafc,#f1f5f9);padding:28px 40px;text-align:center;border-top:1px solid #e2e8f0}
  .footer-brand{display:flex;align-items:center;justify-content:center;gap:8px;font-size:14px;font-weight:700;color:#1e293b;margin-bottom:8px}
  .footer-brand svg{width:18px;height:18px}
  .footer p{margin:0;font-size:12px;color:#94a3b8}

  @php
    $typeConfig = [
      'new_material'   => ['bg'=>'#eff6ff','text'=>'#2563eb','hdr'=>'#dbeafe','badge_bg'=>'#2563eb','label'=>'New Material'],
      'assignment'     => ['bg'=>'#fff7ed','text'=>'#ea580c','hdr'=>'#fed7aa','badge_bg'=>'#ea580c','label'=>'Assignment'],
      'quiz'           => ['bg'=>'#fdf4ff','text'=>'#9333ea','hdr'=>'#e9d5ff','badge_bg'=>'#9333ea','label'=>'Quiz'],
      'live_session'   => ['bg'=>'#f0fdf4','text'=>'#16a34a','hdr'=>'#bbf7d0','badge_bg'=>'#16a34a','label'=>'Live Session'],
      'grade_released' => ['bg'=>'#fff1f2','text'=>'#e11d48','hdr'=>'#fecdd3','badge_bg'=>'#e11d48','label'=>'Grade Released'],
    ];
    $cfg = $typeConfig[$updateType] ?? $typeConfig['new_material'];
  @endphp
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="header-top">
      <div class="logo">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M2 17L12 22L22 17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <span class="brand">APES UDOM</span>
    </div>
    <span class="badge" style="background:{{ $cfg['badge_bg'] }};color:#fff">{{ $cfg['label'] }}</span>
    <h1 style="color:{{ $cfg['text'] }}">{{ $courseName }}</h1>
    <p>AI Powered E-Learning System</p>
  </div>
  <div class="body">
    <p style="font-size:16px;font-weight:600;color:#1e293b;margin:24px 0 16px">Hello {{ $studentName }},</p>
    <p style="font-size:15px;color:#475569;line-height:1.7;margin:0 0 24px">
      There's a new update in <strong>{{ $courseName }}</strong> that requires your attention.
    </p>

    <div class="card" style="background:{{ $cfg['bg'] }};border:1px solid {{ $cfg['hdr'] }}">
      <p class="card-title" style="color:{{ $cfg['text'] }}">{{ $activityName }}</p>
      <p class="card-desc" style="color:#475569">{{ $description }}</p>
      @if($dueDate)
      <p class="meta" style="color:#64748b">📅 Due: <strong>{{ $dueDate }}</strong></p>
      @endif
    </div>

    <div class="btn-wrap">
      <a href="{{ $actionUrl }}" class="btn" style="background:{{ $cfg['badge_bg'] }}">
        Open in APES UDOM
      </a>
    </div>

    <hr class="divider">
    <p style="font-size:13px;color:#94a3b8;line-height:1.6;text-align:center">
      You are receiving this because you are enrolled in <strong>{{ $courseName }}</strong>.<br>
      Manage your notification preferences inside the apes udom site.
    </p>
  </div>
  <div class="footer">
    <p class="footer-brand">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M2 17L12 22L22 17" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      APES UDOM
    </p>
    <p>© {{ date('Y') }} APES UDOM · All rights reserved</p>
  </div>
</div>
</body>
</html>
