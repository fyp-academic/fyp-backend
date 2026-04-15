<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Update – EduAI LMS</title>
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  .wrapper{max-width:600px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .header{padding:40px 40px 32px;text-align:center}
  .header h1{margin:0;font-size:22px;font-weight:700;letter-spacing:-.3px}
  .header p{margin:6px 0 0;font-size:14px}
  .badge{display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px}
  .body{padding:0 40px 40px}
  .card{border-radius:12px;padding:24px;margin-bottom:24px}
  .card-title{font-size:16px;font-weight:700;margin:0 0 8px}
  .card-desc{font-size:14px;line-height:1.7;margin:0 0 4px}
  .meta{font-size:13px;margin:0}
  .btn{display:inline-block;color:#fff;text-decoration:none;padding:13px 32px;border-radius:10px;font-size:14px;font-weight:600}
  .btn-wrap{text-align:center;margin:28px 0 0}
  .divider{border:none;border-top:1px solid #e2e8f0;margin:32px 0}
  .footer{background:#f8fafc;padding:24px 40px;text-align:center}
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
  <div class="header" style="background:{{ $cfg['hdr'] }}">
    <span class="badge" style="background:{{ $cfg['badge_bg'] }};color:#fff">{{ $cfg['label'] }}</span>
    <h1 style="color:{{ $cfg['text'] }}">{{ $courseName }}</h1>
    <p style="color:#64748b">EduAI Learning Management System</p>
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
        Open in EduAI LMS
      </a>
    </div>

    <hr class="divider">
    <p style="font-size:13px;color:#94a3b8;line-height:1.6;text-align:center">
      You are receiving this because you are enrolled in <strong>{{ $courseName }}</strong>.<br>
      Manage your notification preferences inside the LMS.
    </p>
  </div>
  <div class="footer">
    <p>© {{ date('Y') }} EduAI LMS · All rights reserved</p>
  </div>
</div>
</body>
</html>
