<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Insights – APES UDOM</title>
<style>
  body{margin:0;padding:0;background:linear-gradient(135deg,#f1f5f9,#e0e7ff);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;min-height:100vh}
  .container{padding:40px 20px}
  .wrapper{max-width:600px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.12)}
  .header{background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:48px 40px 40px;text-align:center;position:relative}
  .header::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#22c55e,#4f46e5,#7c3aed)}
  .logo{width:56px;height:56px;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:2px solid rgba(255,255,255,.3)}
  .logo svg{width:32px;height:32px}
  .header h1{color:#fff;margin:0 0 6px;font-size:24px;font-weight:700;letter-spacing:-.3px}
  .header p{color:rgba(255,255,255,.85);margin:0;font-size:14px}
  .body{padding:40px}
  .tier-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:999px;font-size:13px;font-weight:600;margin-bottom:20px}
  .profile-chip{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;font-size:13px;color:#475569;margin-bottom:24px}
  .rec-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin-bottom:14px}
  .rec-title{font-size:14px;font-weight:700;color:#1e293b;margin:0 0 6px}
  .rec-desc{font-size:13px;color:#64748b;line-height:1.6;margin:0 0 8px}
  .impact{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase}
  .btn{display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;padding:13px 32px;border-radius:10px;font-size:14px;font-weight:600}
  .btn-wrap{text-align:center;margin:28px 0 0}
  .divider{border:none;border-top:1px solid #e2e8f0;margin:32px 0}
  .footer{background:linear-gradient(180deg,#f8fafc,#f1f5f9);padding:32px 40px;text-align:center;border-top:1px solid #e2e8f0}
  .footer-brand{font-size:15px;font-weight:700;color:#1e293b;margin:0 0 8px;display:flex;align-items:center;justify-content:center;gap:8px}
  .footer-brand svg{width:20px;height:20px}
  .footer-tagline{font-size:13px;color:#64748b;margin:0}
  .footer-divider{width:40px;height:3px;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:2px;margin:16px auto}
  .footer-legal{font-size:12px;color:#94a3b8;margin:12px 0 0}
  @media(max-width:480px){.container{padding:20px 16px}.header{padding:36px 24px 28px}.body{padding:32px 24px}}
</style>
</head>
<body>
<div class="container">
<div class="wrapper">
@php
  $tierConfig = [
    'green'    => ['bg'=>'#dcfce7','text'=>'#16a34a','dot'=>'🟢','label'=>'On Track'],
    'amber'    => ['bg'=>'#fef9c3','text'=>'#ca8a04','dot'=>'🟡','label'=>'Needs Attention'],
    'red'      => ['bg'=>'#fee2e2','text'=>'#dc2626','dot'=>'🔴','label'=>'At Risk'],
    'critical' => ['bg'=>'#fce7f3','text'=>'#be185d','dot'=>'🚨','label'=>'Critical'],
  ];
  $tier = $tierConfig[$riskTier] ?? $tierConfig['amber'];

  $impactColors = [
    'high'   => ['bg'=>'#fee2e2','text'=>'#dc2626'],
    'medium' => ['bg'=>'#fef9c3','text'=>'#ca8a04'],
    'low'    => ['bg'=>'#dcfce7','text'=>'#16a34a'],
  ];

  $profileLabels = [
    'H' => 'Humanitarian (H)',
    'A' => 'Analytical (A)',
    'T' => 'Theoretical (T)',
    'C' => 'Creative (C)',
    'mixed' => 'Mixed Profile',
  ];
@endphp

  <div class="header">
    <div class="logo">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M2 17L12 22L22 17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M2 12L12 17L22 12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h1>🤖 AI Learning Insights</h1>
    <p>Personalised analysis for {{ $courseName }}</p>
  </div>

  <div class="body">
    <p style="font-size:16px;font-weight:600;color:#1e293b;margin:0 0 6px">Hello {{ $userName }},</p>
    <p style="font-size:15px;color:#64748b;line-height:1.8;margin:0 0 24px">
      Your <strong>APES UDOM</strong> AI engine has analysed your activity patterns and generated personalised recommendations to help you succeed in your learning journey.
    </p>

    <span class="tier-badge" style="background:{{ $tier['bg'] }};color:{{ $tier['text'] }}">
      {{ $tier['dot'] }} Status: {{ $tier['label'] }}
    </span><br>

    <span class="profile-chip">
      🧠 Learner Profile: <strong>{{ $profileLabels[$profileType] ?? $profileType }}</strong>
    </span>

    <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin:0 0 14px">
      📋 Your Personalised Recommendations
    </h3>

    @forelse($recommendations as $rec)
    @php
      $impact = $impactColors[$rec['impact_level'] ?? 'medium'] ?? $impactColors['medium'];
    @endphp
    <div class="rec-item">
      <p class="rec-title">{{ $rec['title'] ?? '' }}</p>
      <p class="rec-desc">{{ $rec['description'] ?? '' }}</p>
      <span class="impact" style="background:{{ $impact['bg'] }};color:{{ $impact['text'] }}">
        {{ strtoupper($rec['impact_level'] ?? 'medium') }} IMPACT
      </span>
    </div>
    @empty
    <p style="font-size:14px;color:#94a3b8;text-align:center">No recommendations at this time.</p>
    @endforelse

    <div class="btn-wrap">
      <a href="{{ $actionUrl }}" class="btn">View Full AI Insights</a>
    </div>

    <hr class="divider">
    <p style="font-size:13px;color:#64748b;line-height:1.7;text-align:center">
      These insights are generated by the <strong>APES UDOM</strong> adaptive AI engine based on your engagement patterns in <strong>{{ $courseName }}</strong>.
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
    <p class="footer-tagline">AI Powered E-Learning System · University of Dodoma</p>
    <div class="footer-divider"></div>
    <p class="footer-legal">© {{ date('Y') }} APES UDOM. All rights reserved.</p>
    <p class="footer-legal">Manage notification preferences in your profile settings.</p>
  </div>
</div>
</div>
</body>
</html>
