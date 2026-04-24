<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FYP API Documentation</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif'],mono:['JetBrains Mono','monospace']}}}}</script>
<style>
:root{--nav:52px}
body{font-family:'Inter',sans-serif}
.sidebar{height:calc(100vh - var(--nav));top:var(--nav)}
.main{height:calc(100vh - var(--nav));top:var(--nav)}
code,pre{font-family:'JetBrains Mono',monospace}
.mp{background:#2563eb;color:#fff}.mg{background:#16a34a;color:#fff}
.mu{background:#d97706;color:#fff}.md{background:#dc2626;color:#fff}
.mh{background:#7c3aed;color:#fff}
.acc-body{display:none}.acc-body.open{display:block}
.chev{transition:transform .2s}.chev.open{transform:rotate(180deg)}
.nl{transition:background .15s}.nl.active{background:#1e3a5f;color:#fff}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:#f1f5f9}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
.code-block{background:#0f172a;color:#e2e8f0;border-radius:8px;padding:14px 16px;font-size:11px;line-height:1.7;overflow-x:auto;white-space:pre}
.status-ok{background:#dcfce7;color:#166534}.status-err{background:#fee2e2;color:#991b1b}
.status-warn{background:#fef9c3;color:#854d0e}.role-chip{font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px}
</style>
</head>
<body class="bg-slate-50 text-gray-900">

{{-- NAVBAR --}}
<nav class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-5 h-[52px]" style="background:#0f3d4e">
  <div class="flex items-center gap-2.5">
    <div class="w-8 h-8 rounded-md flex items-center justify-center" style="background:#1a6b87">
      <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0112 21a12.083 12.083 0 01-6.16-17.422L12 14z"/></svg>
    </div>
    <span class="text-white font-bold text-base tracking-wide">FYP API</span>
    <span class="text-xs font-semibold px-2 py-0.5 rounded" style="background:#1a6b87;color:#7dd3e8">V1</span>
  </div>
  <div class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-mono" style="background:#072e3a;color:#7dd3e8;border:1px solid #1a6b87">
    https://api.codagenz.com/api/v1
  </div>
</nav>

{{-- LAYOUT --}}
<div class="flex pt-[52px]">

{{-- SIDEBAR --}}
<aside class="sidebar w-60 fixed left-0 bg-white border-r border-gray-200 overflow-y-auto flex-shrink-0">
  <div class="px-3 pt-4 pb-6">
    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-3 px-2">Navigation</p>
    @php
    $navSections = [
      ['auth',          'Authentication',           [['POST','Register'],['POST','Login'],['POST','Forgot Password'],['POST','Reset Password'],['GET','Verify Email'],['POST','Resend Verification'],['GET','Current User'],['POST','Logout']]],
      ['dashboards',    'Dashboards',               [['GET','Admin Overview'],['GET','Instructor Snapshot'],['GET','Student Hub']]],
      ['courses',       'Courses & Enrollment',     [['GET','List Courses'],['POST','Create Course'],['GET','Get Course'],['PUT','Update Course'],['DELETE','Delete Course'],['GET','Participants'],['POST','Enroll User'],['DELETE','Unenroll User'],['POST','Self Enroll'],['DELETE','Leave Course']]],  
      ['sections',      'Sections & Activities',    [['GET','List Sections'],['POST','Create Section'],['PUT','Update Section'],['DELETE','Delete Section'],['GET','List Activities'],['POST','Create Activity'],['PUT','Update Activity'],['DELETE','Delete Activity']]],
      ['grades',        'Grades & Gradebook',       [['GET','Course Gradebook'],['GET','Get Grade Item'],['POST','Submit Grade'],['GET','Student Grades']]],
      ['categories',    'Categories',               [['GET','List Categories'],['POST','Create Category'],['PUT','Update Category'],['DELETE','Delete Category']]],
      ['notifications', 'Notifications',            [['GET','List Notifications'],['PATCH','Mark as Read'],['POST','Mark All Read'],['DELETE','Delete Notification']]],
      ['messaging',     'Messaging',                [['GET','List Conversations'],['POST','Create Conversation'],['GET','Get Messages'],['POST','Send Message'],['PATCH','Mark Messages Read']]],
      ['quiz',          'Quiz & Question Bank',     [['GET','List Questions'],['POST','Create Question'],['PUT','Update Question'],['DELETE','Delete Question'],['GET','List Answers'],['POST','Create Answer']]],
      ['assignment',    'Assignments',              [['GET','List Submissions'],['POST','Submit Work'],['GET','View Submission'],['PUT','Grade Submission']]],
      ['attendance',    'Attendance',               [['GET','List Sessions'],['POST','Create Session'],['GET','Session Logs'],['POST','Record Attendance'],['POST','Bulk Record']]],
      ['book',          'Book',                     [['GET','List Chapters'],['POST','Create Chapter'],['PUT','Update Chapter'],['DELETE','Delete Chapter']]],
      ['checklist',     'Checklist',                [['GET','List Items'],['POST','Create Item'],['PUT','Update Item'],['DELETE','Delete Item']]],
      ['choice',        'Choice (Poll)',            [['GET','List Options'],['POST','Create Option'],['POST','Submit Response'],['GET','Poll Results']]],
      ['certificate',   'Certificate',              [['GET','View Template'],['POST','Save Template'],['GET','List Issues'],['POST','Issue Certificate']]],
      ['dbactivity',    'Database Activity',        [['GET','List Fields'],['POST','Create Field'],['DELETE','Delete Field'],['GET','List Entries'],['POST','Create Entry'],['PATCH','Approve Entry'],['DELETE','Delete Entry']]],
      ['feedback_tool', 'Feedback',                 [['GET','List Questions'],['POST','Create Question'],['DELETE','Delete Question'],['GET','List Responses'],['POST','Submit Responses']]],
      ['folder',        'Folder',                   [['GET','List Files'],['POST','Add File'],['DELETE','Remove File']]],
      ['forum',         'Forum',                    [['GET','List Discussions'],['POST','Start Discussion'],['GET','List Posts'],['POST','Reply'],['PATCH','Toggle Lock'],['PATCH','Toggle Pin']]],
      ['glossary',      'Glossary',                 [['GET','List Entries'],['POST','Create Entry'],['PUT','Update Entry'],['PATCH','Approve Entry'],['DELETE','Delete Entry']]],
      ['lesson',        'Lesson',                   [['GET','List Pages'],['POST','Create Page'],['PUT','Update Page'],['DELETE','Delete Page']]],
      ['scorm',         'SCORM',                    [['GET','List Tracks'],['POST','Record Track'],['GET','Progress Summary']]],
      ['ai',            'AI Insights',              [['GET','Performance Snapshots'],['GET','Skill Metrics'],['GET','At-Risk Students'],['GET','AI Suggestions'],['GET','Content Recommendations'],['POST','Generate Questions'],['GET','Generated Questions'],['PATCH','Update Question Status'],['GET','Activity Performance'],['GET','Weekly Engagement']]],
      ['pipeline',      'Learner Analytics',        [['GET','Learner Profile'],['POST','Set Profile'],['GET','Behavioral Signals'],['GET','Cognitive Signals'],['GET','Emotional Signals'],['POST','Submit Pulse'],['GET','Risk Score'],['GET','All Risk Scores'],['GET','Interventions'],['POST','Create Intervention'],['GET','Feedback Evaluation'],['POST','Submit Evaluation'],['GET','Drift Logs']]],
      ['profile',       'Profile & Preferences',    [['GET','Get Profile'],['PUT','Update Profile'],['GET','Preferences'],['PUT','Update Preferences']]],
    ];
    $mc=['POST'=>'mp','GET'=>'mg','PUT'=>'mu','DELETE'=>'md','PATCH'=>'mh'];
    @endphp
    @foreach($navSections as [$sid,$slabel,$sitems])
    <p class="text-[9px] font-semibold uppercase tracking-widest text-gray-400 mt-4 mb-1 px-2">{{ $slabel }}</p>
    @foreach($sitems as [$m,$lbl])
    <a href="#{{ $sid }}" data-section="{{ $sid }}"
       class="nl flex items-center gap-2 px-2 py-1 rounded mb-0.5 text-xs text-gray-700 hover:bg-gray-100 cursor-pointer">
      <span class="text-[8px] font-bold px-1.5 py-0.5 rounded uppercase flex-shrink-0 {{ $mc[$m] ?? 'mp' }}">{{ $m }}</span>
      <span class="truncate">{{ $lbl }}</span>
    </a>
    @endforeach
    @endforeach
    <div class="h-4"></div>
  </div>
</aside>

{{-- MAIN CONTENT --}}
<main class="main ml-60 flex-1 overflow-y-auto bg-slate-50">
<div class="px-8 py-7">

<h1 class="text-2xl font-bold text-gray-900 mb-1">API Reference</h1>
<p class="text-gray-500 text-sm mb-5 leading-relaxed">All endpoints live under <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs text-gray-800">/api/v1</code>, return JSON, and use Laravel Sanctum tokens. Send <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs text-gray-800">Authorization: Bearer &lt;token&gt;</code> on protected routes.</p>

@php
function sec($id,$icon,$title,$sub){return "<div id=\"$id\" class=\"mb-10\"><div class=\"flex items-center gap-3 mb-1\"><div class=\"w-7 h-7 rounded flex items-center justify-center bg-gray-800\">$icon</div><h2 class=\"text-lg font-bold text-gray-900\">$title</h2></div><p class=\"text-xs text-gray-400 mb-4 ml-10\">$sub</p><div class=\"bg-white rounded-xl border border-gray-200 overflow-hidden divide-y divide-gray-100\">";}
function endsec(){return "</div></div>";}
$svgK='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
$svgD='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>';
$svgC='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>';
$svgS='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>';
$svgG='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>';
$svgT='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>';
$svgN='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>';
$svgM='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>';
$svgQ='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
$svgA='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>';
$svgP='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>';
$svgU='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
$svgAsn='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
$svgAtt='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>';
$svgBk='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>';
$svgCl='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>';
$svgCh='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>';
$svgCr='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946A3.42 3.42 0 017.835 4.697z"/></svg>';
$svgDb='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>';
$svgFb='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>';
$svgFo='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>';
$svgFr='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>';
$svgGl='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>';
$svgLe='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>';
$svgSc='<svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>';
@endphp

{{-- ═══════════════════════════════════════════ AUTHENTICATION ══ --}}
{!! sec('auth',$svgK,'Authentication','Account lifecycle — register, login, token management, and email verification.') !!}
@php $authEP=[
['POST','Register','/api/v1/auth/register',false,['Public'],'Account creation for students, instructors, and admins.',
 '{"name":"Alice Thompson","email":"alice@university.edu","password":"secret123","password_confirmation":"secret123","role":"student"}',
 '{"message":"Account created. Please verify your email.","user":{"id":"p1","name":"Alice Thompson","email":"alice@university.edu","role":"student","join_date":"2025-09-01"},"token":"1|AbCdEfGhIjKl"}',
 [['201','Created','Registration successful, verification email sent'],['422','Unprocessable Entity','Email already taken or password mismatch']]],

['POST','Login','/api/v1/auth/login',false,['Public'],'Authenticate and receive a Sanctum bearer token.',
 '{"email":"alice@university.edu","password":"secret123"}',
 '{"user":{"id":"p1","name":"Alice Thompson","email":"alice@university.edu","role":"student"},"token":"2|XyZaBcDeFgHi"}',
 [['200','OK','Login successful'],['401','Unauthorized','Invalid credentials'],['422','Unprocessable Entity','Validation error']]],

['POST','Forgot Password','/api/v1/auth/forgot-password',false,['Public'],'Send a signed password-reset link to the given email.',
 '{"email":"alice@university.edu"}',
 '{"message":"We have emailed your password reset link."}',
 [['200','OK','Reset link sent'],['400','Bad Request','Email not found or throttled'],['422','Unprocessable Entity','Invalid email format']]],

['POST','Reset Password','/api/v1/auth/reset-password',false,['Public'],'Reset the password using the signed token from the reset email.',
 '{"token":"abc123","email":"alice@university.edu","password":"newSecret","password_confirmation":"newSecret"}',
 '{"message":"Your password has been reset."}',
 [['200','OK','Password reset successful'],['400','Bad Request','Token expired or invalid']]],

['GET','Verify Email','/api/v1/auth/verify-email/{id}/{hash}',false,['Public'],'Confirm the signed link sent after registration.',
 null,
 '{"message":"Email verified successfully."}',
 [['200','OK','Email verified'],['403','Forbidden','Invalid or tampered signature']]],

['POST','Resend Verification','/api/v1/auth/email/resend',true,['Student','Instructor','Admin'],'Re-send the verification email for an unverified account.',
 null,
 '{"message":"Verification email resent."}',
 [['200','OK','Email sent'],['400','Bad Request','Email already verified']]],

['GET','Current User','/api/v1/auth/me',true,['Student','Instructor','Admin'],'Return the authenticated user\'s full profile.',
 null,
 '{"id":"p1","name":"Alice Thompson","email":"alice@university.edu","role":"student","department":"Computer Science","institution":"University of Technology","country":"United States","timezone":"America/New_York","language":"English","bio":null,"join_date":"2025-09-01","last_access":"2 hours ago","enrolled_courses":3}',
 [['200','OK','Profile returned'],['401','Unauthorized','Token missing or expired']]],

['POST','Logout','/api/v1/auth/logout',true,['Student','Instructor','Admin'],'Revoke the current Sanctum token.',
 null,
 '{"message":"Logged out successfully."}',
 [['200','OK','Token revoked'],['401','Unauthorized','No valid token provided']]],
]; @endphp
@include('_ep_loop',['eps'=>$authEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ DASHBOARDS ══════ --}}
{!! sec('dashboards',$svgD,'Dashboards','Role-specific summary views aggregating KPIs for each user type.') !!}
@php $dashEP=[
['GET','Admin Overview','/api/v1/dashboard/admin',true,['Admin'],'Platform-wide totals: users, courses, enrollments, system health.',
 null,
 '{"total_users":13,"total_courses":5,"total_enrollments":18,"active_courses":4,"system_health":"ok"}',
 [['200','OK','Stats returned'],['401','Unauthorized'],['403','Forbidden','Admin role required']]],

['GET','Instructor Snapshot','/api/v1/dashboard/instructor',true,['Instructor'],'Active courses, total enrollments, weekly engagement, and pending grading for the instructor.',
 null,
 '{"active_courses":[{"id":"course1","name":"Introduction to Python Programming","enrolled_students":142}],"total_enrollments":240,"weekly_engagement":[{"day_label":"Mon","active_students":120,"submissions":45}],"pending_grading_tasks":[{"activity_id":"a6","activity_name":"Assignment 1: FizzBuzz","ungraded_count":3}]}',
 [['200','OK','Snapshot returned'],['401','Unauthorized'],['403','Forbidden','Instructor role required']]],

['GET','Student Hub','/api/v1/dashboard/student',true,['Student'],'Enrolled courses, progress, upcoming due dates, and recent notifications.',
 null,
 '{"enrolled_courses":[{"id":"course1","name":"Introduction to Python Programming","progress":85,"next_due":"2026-02-15"}],"overall_progress":71,"upcoming_due_dates":[{"activity_name":"Quiz 2: Functions","due_date":"2026-02-15"}],"recent_notifications":[{"id":"n1","title":"Quiz Submission","message":"Alice Thompson submitted Quiz 1","timestamp":"2 minutes ago","read":false}]}',
 [['200','OK','Hub data returned'],['401','Unauthorized'],['403','Forbidden','Student role required']]],
]; @endphp
@include('_ep_loop',['eps'=>$dashEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ COURSES ══════════ --}}
{!! sec('courses',$svgC,'Courses & Enrollment','CRUD for courses and participant enrollment.') !!}
@php $courseEP=[
['GET','List Courses','/api/v1/courses',true,['Admin','Instructor','Student'],'Paginated course list; filter by status, category, instructor.',
 null,
 '{"data":[{"id":"course1","name":"Introduction to Python Programming","short_name":"PYTH101","category_name":"Web Development","instructor_name":"Dr. Sarah Johnson","enrolled_students":142,"status":"active","format":"topics","start_date":"2026-01-15","end_date":"2026-06-15","tags":["python","programming","beginner"]}],"meta":{"total":5}}',
 [['200','OK','List returned'],['401','Unauthorized']]],

['POST','Create Course','/api/v1/courses',true,['Admin','Instructor'],'Create a new course.',
 '{"name":"Introduction to Python Programming","short_name":"PYTH101","category_id":"cat2","format":"topics","start_date":"2026-01-15","end_date":"2026-06-15","description":"Learn Python from scratch","tags":["python","beginner"],"visibility":"shown"}',
 '{"message":"Course created.","data":{"id":"course1","name":"Introduction to Python Programming","status":"draft"}}',
 [['201','Created','Course created'],['401','Unauthorized'],['422','Unprocessable Entity','Validation failed']]],

['GET','Get Course','/api/v1/courses/{id}',true,['Admin','Instructor','Student'],'Full detail for one course.',
 null,
 '{"data":{"id":"course1","name":"Introduction to Python Programming","short_name":"PYTH101","description":"Learn Python from scratch","category_id":"cat2","category_name":"Web Development","instructor_id":"user1","instructor_name":"Dr. Sarah Johnson","enrolled_students":142,"status":"active","visibility":"shown","format":"topics","start_date":"2026-01-15","end_date":"2026-06-15","language":"English","tags":["python","programming","beginner"],"max_students":null}}',
 [['200','OK','Course returned'],['401','Unauthorized'],['404','Not Found','Course not found']]],

['PUT','Update Course','/api/v1/courses/{id}',true,['Admin','Instructor'],'Update course metadata, status, or visibility.',
 '{"name":"Python Programming Masterclass","status":"active","visibility":"shown","max_students":200}',
 '{"message":"Course updated.","data":{"id":"course1","name":"Python Programming Masterclass","status":"active"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Course','/api/v1/courses/{id}',true,['Admin'],'Permanently remove a course and all related data.',
 null,
 '{"message":"Course deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['403','Forbidden','Admin only'],['404','Not Found']]],

['GET','List Participants','/api/v1/courses/{id}/participants',true,['Admin','Instructor'],'All enrollments with role, progress, last-access.',
 null,
 '{"data":[{"id":"e1","user_id":"p1","user_name":"Alice Thompson","role":"student","enrolled_date":"2026-01-15","last_access":"2 hours ago","progress":85,"groups":["Group A"]},{"id":"e7","user_id":"p7","user_name":"Grace Chen","role":"teaching_assistant","progress":100,"groups":[]}],"course_id":"course1"}',
 [['200','OK','Participants returned'],['401','Unauthorized'],['403','Forbidden']]],

['POST','Enroll User','/api/v1/courses/{id}/enroll',true,['Admin','Instructor'],'Enroll a user with a specified role.',
 '{"user_id":"p1","role":"student"}',
 '{"message":"User enrolled."}',
 [['201','Created','Enrolled'],['401','Unauthorized'],['403','Forbidden'],['422','Unprocessable Entity','User already enrolled']]],

['DELETE','Unenroll User','/api/v1/courses/{id}/enroll/{userId}',true,['Admin','Instructor'],'Remove a user\'s enrollment.',
 null,
 '{"message":"User unenrolled."}',
 [['200','OK','Unenrolled'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],

['POST','Self Enroll','/api/v1/courses/{id}/join',true,['Student'],'Student self-enrolls into a course. No request body needed — uses the authenticated user\'s identity.',
 null,
 '{"message":"Successfully enrolled in course.","course_id":"course1","user_id":"p1","role":"student"}',
 [['201','Created','Enrolled successfully'],['401','Unauthorized'],['409','Conflict','Already enrolled in this course'],['422','Unprocessable Entity','Course is full or enrollment is closed']]],

['DELETE','Leave Course','/api/v1/courses/{id}/leave',true,['Student'],'Student withdraws from a course they are enrolled in.',
 null,
 '{"message":"Successfully left the course.","course_id":"course1","user_id":"p1"}',
 [['200','OK','Successfully withdrawn'],['401','Unauthorized'],['404','Not Found','Not enrolled in this course']]],
]; @endphp
@include('_ep_loop',['eps'=>$courseEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ SECTIONS ═════════ --}}
{!! sec('sections',$svgS,'Sections & Activities','Manage course sections (weeks/topics) and individual learning activities.') !!}
@php $secEP=[
['GET','List Sections','/api/v1/courses/{id}/sections',true,['Admin','Instructor','Student'],'All sections for a course ordered by sort_order.',
 null,
 '{"data":[{"id":"sec1-c1","course_id":"course1","title":"Week 1: Introduction to Python","summary":null,"sort_order":1,"visible":true,"collapsed":false},{"id":"sec2-c1","course_id":"course1","title":"Week 2: Control Flow","sort_order":2,"visible":true}],"course_id":"course1"}',
 [['200','OK','Sections returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Section','/api/v1/courses/{id}/sections',true,['Admin','Instructor'],'Add a new week or topic section.',
 '{"title":"Week 5: Advanced OOP","sort_order":5,"visible":true}',
 '{"message":"Section created.","data":{"id":"sec5-c1","course_id":"course1","title":"Week 5: Advanced OOP","sort_order":5,"visible":true}}',
 [['201','Created','Section created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['PUT','Update Section','/api/v1/courses/{id}/sections/{sectionId}',true,['Admin','Instructor'],'Rename, reorder, or toggle visibility.',
 '{"title":"Week 1: Python Foundations","sort_order":1,"visible":true}',
 '{"message":"Section updated.","data":{"course_id":"course1","section_id":"sec1-c1","title":"Week 1: Python Foundations"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Section','/api/v1/courses/{id}/sections/{sectionId}',true,['Admin','Instructor'],'Remove a section and cascade-delete all activities in it.',
 null,
 '{"message":"Section deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],

['GET','List Activities','/api/v1/sections/{id}/activities',true,['Admin','Instructor','Student'],'All activities in a section ordered by sort_order.',
 null,
 '{"data":[{"id":"a4","section_id":"sec1-c1","course_id":"course1","type":"quiz","name":"Quiz 1: Python Basics","due_date":"2026-02-01","visible":true,"completion_status":"completed","grade_max":100,"sort_order":2},{"id":"a3","section_id":"sec1-c1","type":"file","name":"Python Installation Guide.pdf","grade_max":null}],"section_id":"sec1-c1"}',
 [['200','OK','Activities returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Activity','/api/v1/sections/{id}/activities',true,['Admin','Instructor'],'Add a new activity. Types: assignment | attendance | bigbluebutton | book | checklist | choice | certificate | database | feedback | file | folder | forum | glossary | h5p | ims_content_package | lesson | page | quiz | scorm | text_and_media_area. Use the settings JSON field for tool-specific configuration.',
 '{"type":"quiz","name":"Quiz 3: OOP Basics","visible":true,"grade_max":100,"due_date":"2026-03-01","sort_order":0,"settings":null}',
 '{"message":"Activity created.","data":{"section_id":"sec4-c1","type":"quiz","name":"Quiz 3: OOP Basics","grade_max":100}}',
 [['201','Created','Activity created'],['401','Unauthorized'],['422','Unprocessable Entity','Invalid type enum']]],

['PUT','Update Activity','/api/v1/activities/{id}',true,['Admin','Instructor'],'Edit activity details, due date, visibility, or grade max.',
 '{"name":"Quiz 1: Python Fundamentals","due_date":"2026-02-05","visible":true,"grade_max":100}',
 '{"message":"Activity updated.","data":{"id":"a4","name":"Quiz 1: Python Fundamentals","due_date":"2026-02-05"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Activity','/api/v1/activities/{id}',true,['Admin','Instructor'],'Remove an activity; also deletes associated grade items.',
 null,
 '{"message":"Activity deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$secEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ GRADES ═══════════ --}}
{!! sec('grades',$svgG,'Grades & Gradebook','Retrieve and submit grades for course activities.') !!}
@php $gradeEP=[
['GET','Course Gradebook','/api/v1/courses/{id}/grades',true,['Admin','Instructor'],'All grade items and student submissions for a course.',
 null,
 '{"data":[{"id":"g1","course_id":"course1","activity_id":"a4","activity_name":"Quiz 1: Python Basics","activity_type":"quiz","grade_max":100,"grades":[{"student_id":"p1","student_name":"Alice Thompson","grade":92,"percentage":92,"status":"graded"},{"student_id":"p4","student_name":"David Kim","grade":null,"status":"not_submitted"}]},{"id":"g2","activity_name":"Assignment 1: FizzBuzz","grade_max":50}],"course_id":"course1"}',
 [['200','OK','Gradebook returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','Get Grade Item','/api/v1/grade-items/{id}',true,['Admin','Instructor'],'Single grade item with all student grade records.',
 null,
 '{"data":{"id":"g2","course_id":"course1","activity_id":"a6","activity_name":"Assignment 1: FizzBuzz","activity_type":"assignment","grade_max":50,"grades":[{"id":"sg6","student_id":"p1","student_name":"Alice Thompson","grade":48,"percentage":96,"feedback":"Excellent work!","submitted_date":"2026-02-07","status":"graded"},{"id":"sg9","student_id":"p4","student_name":"David Kim","grade":22,"percentage":44,"status":"late"}]}}',
 [['200','OK','Grade item returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Submit Grade','/api/v1/grade-items/{id}/grades',true,['Admin','Instructor'],'Record or update a student grade with optional feedback.',
 '{"student_id":"p4","grade":75,"feedback":"Good improvement, keep it up!","graded_by":"user1"}',
 '{"message":"Grade submitted.","data":{"grade_item_id":"g2","student_id":"p4","grade":75,"percentage":150,"feedback":"Good improvement, keep it up!"}}',
 [['201','Created','Grade recorded'],['401','Unauthorized'],['403','Forbidden'],['422','Unprocessable Entity','Grade exceeds grade_max']]],

['GET','Student Grades','/api/v1/courses/{id}/grades/student/{studentId}',true,['Admin','Instructor','Student'],'All grades for one student across all grade items in a course.',
 null,
 '{"data":[{"grade_item_id":"g1","activity_name":"Quiz 1: Python Basics","grade":92,"percentage":92,"grade_max":100,"status":"graded","submitted_date":"2026-02-01"},{"grade_item_id":"g2","activity_name":"Assignment 1: FizzBuzz","grade":48,"percentage":96,"grade_max":50,"status":"graded"}],"course_id":"course1","student_id":"p1"}',
 [['200','OK','Grades returned'],['401','Unauthorized'],['403','Forbidden','Students can only view own grades']]],
]; @endphp
@include('_ep_loop',['eps'=>$gradeEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ CATEGORIES ════════ --}}
{!! sec('categories',$svgT,'Categories','Hierarchical course categories with parent/child nesting.') !!}
@php $catEP=[
['GET','List Categories','/api/v1/categories',true,['Admin','Instructor','Student'],'All categories with parent/child hierarchy and course counts.',
 null,
 '{"data":[{"id":"cat1","name":"Computer Science","description":"CS and programming courses","parent_id":null,"id_number":"CS001","course_count":8,"child_count":2},{"id":"cat2","name":"Web Development","description":"Frontend and backend development","parent_id":"cat1","id_number":"CS-WEB","course_count":4},{"id":"cat4","name":"Mathematics","parent_id":null,"course_count":5},{"id":"cat6","name":"Business","parent_id":null,"course_count":6}]}',
 [['200','OK','Categories returned'],['401','Unauthorized']]],

['POST','Create Category','/api/v1/categories',true,['Admin'],'Create a new top-level or nested category.',
 '{"name":"Artificial Intelligence","description":"AI and ML fundamentals","parent_id":"cat1","id_number":"CS-AI"}',
 '{"message":"Category created.","data":{"id":"cat8","name":"Artificial Intelligence","parent_id":"cat1","id_number":"CS-AI","course_count":0}}',
 [['201','Created','Category created'],['401','Unauthorized'],['403','Forbidden','Admin only'],['422','Unprocessable Entity']]],

['PUT','Update Category','/api/v1/categories/{id}',true,['Admin'],'Update name, description, or parent.',
 '{"name":"AI & Machine Learning","description":"Comprehensive AI track","parent_id":"cat1"}',
 '{"message":"Category updated.","data":{"id":"cat8","name":"AI & Machine Learning"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],

['DELETE','Delete Category','/api/v1/categories/{id}',true,['Admin'],'Remove category. Returns 422 if courses are still assigned.',
 null,
 '{"message":"Category deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found'],['422','Unprocessable Entity','Courses still assigned to category']]],
]; @endphp
@include('_ep_loop',['eps'=>$catEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ NOTIFICATIONS ═════ --}}
{!! sec('notifications',$svgN,'Notifications','In-app notifications for the authenticated user.') !!}
@php $notifEP=[
['GET','List Notifications','/api/v1/notifications',true,['Admin','Instructor','Student'],'All notifications, newest first, with unread count.',
 null,
 '{"data":[{"id":"n1","user_id":"user1","title":"Quiz Submission","message":"Alice Thompson submitted Quiz 1: Python Basics","timestamp":"2 minutes ago","read":false,"type":"info"},{"id":"n2","user_id":"user1","title":"Assignment Due Soon","message":"Assignment 1: FizzBuzz is due in 24 hours","timestamp":"1 hour ago","read":false,"type":"warning"},{"id":"n3","title":"New Enrollment","message":"5 new students enrolled in Introduction to Python","type":"success","read":false}],"unread_count":3}',
 [['200','OK','Notifications returned'],['401','Unauthorized']]],

['PATCH','Mark as Read','/api/v1/notifications/{id}/read',true,['Admin','Instructor','Student'],'Mark a single notification as read.',
 null,
 '{"message":"Notification marked as read.","id":"n1"}',
 [['200','OK','Marked read'],['401','Unauthorized'],['404','Not Found']]],

['POST','Mark All Read','/api/v1/notifications/mark-all-read',true,['Admin','Instructor','Student'],'Mark every unread notification as read.',
 null,
 '{"message":"All notifications marked as read."}',
 [['200','OK','All marked read'],['401','Unauthorized']]],

['DELETE','Delete Notification','/api/v1/notifications/{id}',true,['Admin','Instructor','Student'],'Permanently delete a notification.',
 null,
 '{"message":"Notification deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$notifEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ MESSAGING ═════════ --}}
{!! sec('messaging',$svgM,'Messaging','One-to-one conversation threads between platform users.') !!}
@php $msgEP=[
['GET','List Conversations','/api/v1/conversations',true,['Admin','Instructor','Student'],'All conversations, sorted by latest message.',
 null,
 '{"data":[{"id":"conv1","owner_user_id":"user1","participant_user_id":"p1","participant_name":"Alice Thompson","participant_role":"Student","last_message":"Thank you for the feedback on my assignment!","last_message_time":"10:32 AM","unread_count":2,"course_id":"course1"},{"id":"conv2","participant_name":"Bob Martinez","last_message":"When is the next quiz scheduled?","unread_count":1}]}',
 [['200','OK','Conversations returned'],['401','Unauthorized']]],

['POST','Create Conversation','/api/v1/conversations',true,['Admin','Instructor','Student'],'Start a new one-to-one thread.',
 '{"recipient_id":"p1","message":"Hi Alice, I reviewed your assignment. Great work overall!"}',
 '{"message":"Conversation created.","data":{"id":"conv4","recipient_id":"p1","message":"Hi Alice, I reviewed your assignment. Great work overall!"}}',
 [['201','Created','Conversation created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Get Messages','/api/v1/conversations/{id}/messages',true,['Admin','Instructor','Student'],'All messages in a thread, chronological order.',
 null,
 '{"data":[{"id":"m1","conversation_id":"conv1","sender_id":"user1","sender_name":"Dr. Sarah Johnson","content":"Hi Alice, I reviewed your assignment. Great work overall!","timestamp":"10:15 AM","read":true},{"id":"m2","sender_id":"p1","sender_name":"Alice Thompson","content":"Thank you so much! I worked really hard on it.","timestamp":"10:20 AM","read":true},{"id":"m4","sender_id":"p1","content":"Thank you for the feedback on my assignment!","timestamp":"10:32 AM","read":false}],"conversation_id":"conv1"}',
 [['200','OK','Messages returned'],['401','Unauthorized'],['403','Forbidden','Not a participant'],['404','Not Found']]],

['POST','Send Message','/api/v1/conversations/{id}/messages',true,['Admin','Instructor','Student'],'Post a new message to a conversation.',
 '{"message":"Section 3 feedback: please expand your error handling."}',
 '{"message":"Message sent.","data":{"conversation_id":"conv1","message":"Section 3 feedback: please expand your error handling."}}',
 [['201','Created','Message sent'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],

['PATCH','Mark Messages Read','/api/v1/conversations/{id}/read',true,['Admin','Instructor','Student'],'Mark all unread messages in a conversation as read.',
 null,
 '{"message":"Messages marked as read.","conversation_id":"conv1"}',
 [['200','OK','Marked read'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$msgEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ QUIZ ════════════ --}}
{!! sec('quiz',$svgQ,'Quiz & Question Bank','Questions (8 types) and answer options with grade fractions.') !!}
@php $quizEP=[
['GET','List Questions','/api/v1/activities/{id}/questions',true,['Admin','Instructor'],'All questions in the question bank for a quiz activity.',
 null,
 '{"data":[{"id":"qq1","activity_id":"a4","type":"multiple_choice","question_text":"Which of the following is the correct way to declare a variable in Python?","category":"Python Basics","default_mark":1,"shuffle_answers":true,"penalty":0},{"id":"qq2","type":"true_false","question_text":"Python is a statically typed programming language.","correct_answer":"False","default_mark":1}],"activity_id":"a4"}',
 [['200','OK','Questions returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Question','/api/v1/activities/{id}/questions',true,['Admin','Instructor'],'Add a question. Types: multiple_choice | true_false | matching | short_answer | numerical | essay | calculated | drag_drop.',
 '{"type":"multiple_choice","question_text":"What is a Python list comprehension?","category":"Python Basics","default_mark":1,"shuffle_answers":true,"penalty":0}',
 '{"message":"Question created.","data":{"id":"qq9","activity_id":"a4","type":"multiple_choice","question_text":"What is a Python list comprehension?"}}',
 [['201','Created','Question created'],['401','Unauthorized'],['422','Unprocessable Entity','Invalid type']]],

['PUT','Update Question','/api/v1/questions/{id}',true,['Admin','Instructor'],'Edit question text, marks, shuffle, or penalty.',
 '{"question_text":"Which keyword is used to define a function in Python?","default_mark":2,"shuffle_answers":true}',
 '{"message":"Question updated.","data":{"id":"qq1","question_text":"Which keyword is used to define a function in Python?","default_mark":2}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Question','/api/v1/questions/{id}',true,['Admin','Instructor'],'Remove a question and all its answer options.',
 null,
 '{"message":"Question deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],

['GET','List Answers','/api/v1/questions/{id}/answers',true,['Admin','Instructor'],'All answer options with grade fractions. grade_fraction: 1.0 = correct, 0 = wrong, negative = penalty.',
 null,
 '{"data":[{"id":"qa1","question_id":"qq1","text":"x = 10","grade_fraction":1.0,"feedback":"Correct! Standard Python variable assignment."},{"id":"qa2","text":"int x = 10;","grade_fraction":0,"feedback":"Incorrect. That is Java/C# syntax."},{"id":"qa3","text":"var x = 10","grade_fraction":0,"feedback":"Incorrect. That is JavaScript."}],"question_id":"qq1"}',
 [['200','OK','Answers returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Answer','/api/v1/questions/{id}/answers',true,['Admin','Instructor'],'Add an answer option with grade fraction and optional feedback.',
 '{"answer_text":"def","grade_fraction":1.0,"feedback":"Correct! def is used to define functions in Python.","sort_order":0}',
 '{"message":"Answer created.","data":{"id":"qa16","question_id":"qq1","answer_text":"def","grade_fraction":1.0}}',
 [['201','Created','Answer created'],['401','Unauthorized'],['422','Unprocessable Entity','grade_fraction out of -1 to 1 range']]],
]; @endphp
@include('_ep_loop',['eps'=>$quizEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ ASSIGNMENT ════════ --}}
{!! sec('assignment',$svgAsn,'Assignments','Student submissions with grading, late detection, and multi-attempt support.') !!}
@php $asnEP=[
['GET','List Submissions','/api/v1/activities/{id}/submissions',true,['Admin','Instructor'],'All submissions for an assignment activity, with student details.',
 null,
 '{"data":[{"id":"sub1","activity_id":"a6","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"status":"graded","submission_text":"def fizzbuzz()...","file_path":null,"submitted_at":"2026-02-07T09:30:00Z","grade":48,"graded_at":"2026-02-08T14:00:00Z","feedback":"Excellent work!","attempt_number":1,"late":false}],"activity_id":"a6"}',
 [['200','OK','Submissions returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Submit Work','/api/v1/activities/{id}/submissions',true,['Student'],'Submit text or file for an assignment. Auto-detects late submissions.',
 '{"submission_text":"def fizzbuzz(n):\n    for i in range(1,n+1):\n        ...","file_path":null,"file_name":null}',
 '{"message":"Submission created.","data":{"id":"sub5","activity_id":"a6","student_id":"p1","status":"submitted","submitted_at":"2026-02-07T09:30:00Z","attempt_number":1,"late":false}}',
 [['201','Created','Submitted'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['GET','View Submission','/api/v1/submissions/{id}',true,['Admin','Instructor','Student'],'Full detail for a single submission with grader info.',
 null,
 '{"data":{"id":"sub1","activity_id":"a6","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"grader":{"id":"user1","name":"Dr. Sarah Johnson"},"status":"graded","submission_text":"def fizzbuzz()...","grade":48,"feedback":"Excellent work!","submitted_at":"2026-02-07T09:30:00Z","graded_at":"2026-02-08T14:00:00Z"}}',
 [['200','OK','Submission returned'],['401','Unauthorized'],['404','Not Found']]],

['PUT','Grade Submission','/api/v1/submissions/{id}/grade',true,['Admin','Instructor'],'Instructor grades a submission with score and feedback.',
 '{"grade":48,"feedback":"Excellent implementation of FizzBuzz. Clean code."}',
 '{"message":"Submission graded.","data":{"id":"sub1","grade":48,"feedback":"Excellent implementation of FizzBuzz. Clean code.","graded_by":"user1","graded_at":"2026-02-08T14:00:00Z","status":"graded"}}',
 [['200','OK','Graded'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],
]; @endphp
@include('_ep_loop',['eps'=>$asnEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ ATTENDANCE ════════ --}}
{!! sec('attendance',$svgAtt,'Attendance','Session-based attendance tracking with per-student status and bulk recording.') !!}
@php $attEP=[
['GET','List Sessions','/api/v1/activities/{id}/attendance-sessions',true,['Admin','Instructor'],'All sessions for an attendance activity, with log counts.',
 null,
 '{"data":[{"id":"sess1","activity_id":"a10","course_id":"course1","title":"Week 1 Lecture","session_date":"2026-01-20T09:00:00Z","duration_minutes":60,"status":"open","logs_count":28}],"activity_id":"a10"}',
 [['200','OK','Sessions returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Session','/api/v1/activities/{id}/attendance-sessions',true,['Admin','Instructor'],'Create a new attendance session.',
 '{"title":"Week 2 Lecture","session_date":"2026-01-27T09:00:00Z","duration_minutes":90,"description":"Introduction to Control Flow"}',
 '{"message":"Session created.","data":{"id":"sess2","activity_id":"a10","title":"Week 2 Lecture","session_date":"2026-01-27T09:00:00Z","status":"open"}}',
 [['201','Created','Session created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Session Logs','/api/v1/attendance-sessions/{id}/logs',true,['Admin','Instructor'],'Attendance logs for a specific session with student details.',
 null,
 '{"data":[{"id":"log1","session_id":"sess1","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"status":"present","remarks":null,"taken_by":"user1"},{"id":"log2","student_id":"p4","student":{"id":"p4","name":"David Kim"},"status":"absent","remarks":"Notified via email"}],"session_id":"sess1"}',
 [['200','OK','Logs returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Record Attendance','/api/v1/attendance-sessions/{id}/logs',true,['Admin','Instructor'],'Record or update a single student\'s attendance. Status: present | absent | late | excused.',
 '{"student_id":"p1","status":"present","remarks":null}',
 '{"message":"Attendance recorded.","data":{"id":"log1","session_id":"sess1","student_id":"p1","status":"present"}}',
 [['200','OK','Recorded'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['POST','Bulk Record','/api/v1/attendance-sessions/{id}/logs/bulk',true,['Admin','Instructor'],'Record attendance for multiple students at once.',
 '{"records":[{"student_id":"p1","status":"present"},{"student_id":"p4","status":"absent","remarks":"Sick leave"},{"student_id":"p7","status":"late"}]}',
 '{"message":"Bulk attendance recorded.","data":[{"id":"log1","student_id":"p1","status":"present"},{"id":"log2","student_id":"p4","status":"absent"},{"id":"log3","student_id":"p7","status":"late"}]}',
 [['200','OK','Bulk recorded'],['401','Unauthorized'],['422','Unprocessable Entity']]],
]; @endphp
@include('_ep_loop',['eps'=>$attEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ BOOK ═════════════ --}}
{!! sec('book',$svgBk,'Book','Multi-page book resource with ordered chapters and sub-chapters.') !!}
@php $bookEP=[
['GET','List Chapters','/api/v1/activities/{id}/chapters',true,['Admin','Instructor','Student'],'All chapters in a book activity ordered by sort_order.',
 null,
 '{"data":[{"id":"ch1","activity_id":"a11","title":"1. Getting Started","content":"<p>Welcome to Python...</p>","sort_order":0,"sub_chapter":false,"hidden":false},{"id":"ch2","title":"1.1 Installing Python","content":"<p>Download from python.org...</p>","sort_order":1,"sub_chapter":true,"hidden":false}],"activity_id":"a11"}',
 [['200','OK','Chapters returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Chapter','/api/v1/activities/{id}/chapters',true,['Admin','Instructor'],'Add a new chapter or sub-chapter.',
 '{"title":"2. Variables and Data Types","content":"<p>In Python, variables are dynamically typed...</p>","sub_chapter":false}',
 '{"message":"Chapter created.","data":{"id":"ch3","activity_id":"a11","title":"2. Variables and Data Types","sort_order":2,"sub_chapter":false}}',
 [['201','Created','Chapter created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['PUT','Update Chapter','/api/v1/chapters/{id}',true,['Admin','Instructor'],'Edit a chapter title, content, or visibility.',
 '{"title":"1. Getting Started with Python","content":"<p>Updated introduction...</p>","hidden":false}',
 '{"message":"Chapter updated.","data":{"id":"ch1","title":"1. Getting Started with Python"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Chapter','/api/v1/chapters/{id}',true,['Admin','Instructor'],'Remove a chapter.',
 null,
 '{"message":"Chapter deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$bookEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ CHECKLIST ════════ --}}
{!! sec('checklist',$svgCl,'Checklist','Task checklist with required/optional items for student self-tracking.') !!}
@php $clEP=[
['GET','List Items','/api/v1/activities/{id}/checklist-items',true,['Admin','Instructor','Student'],'All items in a checklist, ordered by sort_order.',
 null,
 '{"data":[{"id":"cli1","activity_id":"a12","text":"Read Chapter 1","is_required":true,"checked_by_default":false,"sort_order":0},{"id":"cli2","text":"Watch introductory video","is_required":false,"sort_order":1},{"id":"cli3","text":"Complete pre-quiz","is_required":true,"sort_order":2}],"activity_id":"a12"}',
 [['200','OK','Items returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Item','/api/v1/activities/{id}/checklist-items',true,['Admin','Instructor'],'Add a new checklist item.',
 '{"text":"Submit assignment draft","is_required":true,"checked_by_default":false}',
 '{"message":"Checklist item created.","data":{"id":"cli4","activity_id":"a12","text":"Submit assignment draft","is_required":true,"sort_order":3}}',
 [['201','Created','Item created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['PUT','Update Item','/api/v1/checklist-items/{id}',true,['Admin','Instructor'],'Edit a checklist item text or properties.',
 '{"text":"Read Chapter 1 and 2","is_required":true}',
 '{"message":"Checklist item updated.","data":{"id":"cli1","text":"Read Chapter 1 and 2","is_required":true}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Item','/api/v1/checklist-items/{id}',true,['Admin','Instructor'],'Remove a checklist item.',
 null,
 '{"message":"Checklist item deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$clEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ CHOICE ═══════════ --}}
{!! sec('choice',$svgCh,'Choice (Poll)','Single-choice polling activity with real-time result aggregation.') !!}
@php $chEP=[
['GET','List Options','/api/v1/activities/{id}/choice-options',true,['Admin','Instructor','Student'],'All options with response counts.',
 null,
 '{"data":[{"id":"co1","activity_id":"a13","option_text":"Python","max_responses":null,"sort_order":0,"responses_count":18},{"id":"co2","option_text":"JavaScript","sort_order":1,"responses_count":12},{"id":"co3","option_text":"Java","sort_order":2,"responses_count":8}],"activity_id":"a13"}',
 [['200','OK','Options returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Option','/api/v1/activities/{id}/choice-options',true,['Admin','Instructor'],'Add a poll option.',
 '{"option_text":"Rust","max_responses":null}',
 '{"message":"Option created.","data":{"id":"co4","activity_id":"a13","option_text":"Rust","sort_order":3}}',
 [['201','Created','Option created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['POST','Submit Response','/api/v1/activities/{id}/choice-responses',true,['Student'],'Student votes for one option. Only one vote per student allowed.',
 '{"option_id":"co1"}',
 '{"message":"Response recorded.","data":{"id":"cr1","activity_id":"a13","option_id":"co1","student_id":"p1"}}',
 [['201','Created','Vote recorded'],['401','Unauthorized'],['409','Conflict','Already responded']]],

['GET','Poll Results','/api/v1/activities/{id}/choice-results',true,['Admin','Instructor','Student'],'Aggregated results with percentages.',
 null,
 '{"data":[{"id":"co1","option_text":"Python","count":18,"percentage":47.4},{"id":"co2","option_text":"JavaScript","count":12,"percentage":31.6},{"id":"co3","option_text":"Java","count":8,"percentage":21.1}],"total_responses":38,"activity_id":"a13"}',
 [['200','OK','Results returned'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$chEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ CERTIFICATE ══════ --}}
{!! sec('certificate',$svgCr,'Certificate','Custom certificate templates with automatic or manual issuance.') !!}
@php $crEP=[
['GET','View Template','/api/v1/activities/{id}/certificate',true,['Admin','Instructor'],'Get the certificate template configuration.',
 null,
 '{"data":{"id":"ct1","activity_id":"a14","course_id":"course1","name":"Python Completion Certificate","body_html":"<h1>Certificate of Completion</h1>...","orientation":"landscape","required_activities":["a4","a6"],"min_grade":60,"expiry_days":null},"activity_id":"a14"}',
 [['200','OK','Template returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Save Template','/api/v1/activities/{id}/certificate',true,['Admin','Instructor'],'Create or update the certificate template.',
 '{"name":"Python Completion Certificate","body_html":"<h1>Certificate of Completion</h1><p>This certifies that {{student_name}} has completed {{course_name}}.</p>","orientation":"landscape","required_activities":["a4","a6"],"min_grade":60}',
 '{"message":"Certificate template saved.","data":{"id":"ct1","activity_id":"a14","name":"Python Completion Certificate"}}',
 [['200','OK','Template saved'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','List Issues','/api/v1/activities/{id}/certificate/issues',true,['Admin','Instructor'],'All certificates issued for this activity.',
 null,
 '{"data":[{"id":"ci1","certificate_id":"ct1","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"issued_at":"2026-06-01T10:00:00Z","code":"ABCDEF123456","expires_at":null}],"activity_id":"a14"}',
 [['200','OK','Issues returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Issue Certificate','/api/v1/activities/{id}/certificate/issue',true,['Admin','Instructor'],'Issue a certificate to a specific student.',
 '{"student_id":"p1"}',
 '{"message":"Certificate issued.","data":{"id":"ci2","certificate_id":"ct1","student_id":"p1","issued_at":"2026-06-01T10:00:00Z","code":"XYZABC789012"}}',
 [['201','Created','Certificate issued'],['401','Unauthorized'],['409','Conflict','Already issued'],['422','Unprocessable Entity']]],
]; @endphp
@include('_ep_loop',['eps'=>$crEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ DATABASE ACT. ════ --}}
{!! sec('dbactivity',$svgDb,'Database Activity','Collaborative structured database with custom field definitions and student entries.') !!}
@php $dbEP=[
['GET','List Fields','/api/v1/activities/{id}/db-fields',true,['Admin','Instructor','Student'],'All field definitions for a database activity.',
 null,
 '{"data":[{"id":"df1","activity_id":"a15","name":"Project Title","type":"text","description":"Name of the project","required":true,"sort_order":0},{"id":"df2","name":"URL","type":"url","required":false,"sort_order":1},{"id":"df3","name":"Category","type":"dropdown","options":["Web","Mobile","Data Science"],"sort_order":2}],"activity_id":"a15"}',
 [['200','OK','Fields returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Field','/api/v1/activities/{id}/db-fields',true,['Admin','Instructor'],'Add a new field definition. Types: text | number | date | url | image | file | textarea | checkbox | radio | dropdown | latlong.',
 '{"name":"Description","type":"textarea","description":"Project description","required":true}',
 '{"message":"Field created.","data":{"id":"df4","activity_id":"a15","name":"Description","type":"textarea","required":true,"sort_order":3}}',
 [['201','Created','Field created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['DELETE','Delete Field','/api/v1/db-fields/{id}',true,['Admin','Instructor'],'Remove a field definition.',
 null,
 '{"message":"Field deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],

['GET','List Entries','/api/v1/activities/{id}/db-entries',true,['Admin','Instructor','Student'],'All entries submitted to the database.',
 null,
 '{"data":[{"id":"de1","activity_id":"a15","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"content":{"Project Title":"Python Chat Bot","URL":"https://github.com/alice/chatbot","Category":"Data Science"},"approved":true,"approved_by":"user1"}],"activity_id":"a15"}',
 [['200','OK','Entries returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Entry','/api/v1/activities/{id}/db-entries',true,['Student'],'Submit a new database entry.',
 '{"content":{"Project Title":"Weather Dashboard","URL":"https://github.com/bob/weather","Category":"Web"}}',
 '{"message":"Entry created.","data":{"id":"de2","activity_id":"a15","student_id":"p4","content":{"Project Title":"Weather Dashboard"},"approved":false}}',
 [['201','Created','Entry created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['PATCH','Approve Entry','/api/v1/db-entries/{id}/approve',true,['Admin','Instructor'],'Approve a student entry.',
 null,
 '{"message":"Entry approved.","data":{"id":"de2","approved":true,"approved_by":"user1"}}',
 [['200','OK','Approved'],['401','Unauthorized'],['404','Not Found']]],

['DELETE','Delete Entry','/api/v1/db-entries/{id}',true,['Admin','Instructor'],'Remove a database entry.',
 null,
 '{"message":"Entry deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$dbEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ FEEDBACK ═════════ --}}
{!! sec('feedback_tool',$svgFb,'Feedback','Anonymous or named survey/feedback forms with multiple question types.') !!}
@php $fbEP=[
['GET','List Questions','/api/v1/activities/{id}/feedback-questions',true,['Admin','Instructor'],'All questions in a feedback form.',
 null,
 '{"data":[{"id":"fq1","activity_id":"a16","type":"rating","question_text":"How would you rate this course overall?","options":null,"required":true,"sort_order":0},{"id":"fq2","type":"multichoice","question_text":"Which topics were most helpful?","options":["Variables","Functions","OOP","Error Handling"],"required":false,"sort_order":1},{"id":"fq3","type":"textarea","question_text":"Any additional comments?","sort_order":2}],"activity_id":"a16"}',
 [['200','OK','Questions returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Question','/api/v1/activities/{id}/feedback-questions',true,['Admin','Instructor'],'Add a feedback question. Types: text | textarea | numeric | multichoice | rating | info.',
 '{"type":"rating","question_text":"How clear were the instructions?","required":true}',
 '{"message":"Question created.","data":{"id":"fq4","activity_id":"a16","type":"rating","question_text":"How clear were the instructions?","sort_order":3}}',
 [['201','Created','Question created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['DELETE','Delete Question','/api/v1/feedback-questions/{id}',true,['Admin','Instructor'],'Remove a feedback question and its responses.',
 null,
 '{"message":"Question deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],

['GET','List Responses','/api/v1/activities/{id}/feedback-responses',true,['Admin','Instructor'],'All submitted feedback responses with student and question info.',
 null,
 '{"data":[{"id":"fr1","activity_id":"a16","question_id":"fq1","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"question":{"id":"fq1","question_text":"How would you rate this course overall?"},"response_value":"5"},{"id":"fr2","question_id":"fq2","student_id":"p1","response_value":"Functions,OOP"}],"activity_id":"a16"}',
 [['200','OK','Responses returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Submit Responses','/api/v1/activities/{id}/feedback-responses',true,['Student'],'Submit all feedback answers in a single batch request.',
 '{"answers":[{"question_id":"fq1","response_value":"5"},{"question_id":"fq2","response_value":"Functions,OOP"},{"question_id":"fq3","response_value":"Great course, very well structured!"}]}',
 '{"message":"Feedback submitted.","data":[{"id":"fr4","question_id":"fq1","response_value":"5"},{"id":"fr5","question_id":"fq2","response_value":"Functions,OOP"},{"id":"fr6","question_id":"fq3","response_value":"Great course, very well structured!"}]}',
 [['201','Created','Feedback submitted'],['401','Unauthorized'],['422','Unprocessable Entity']]],
]; @endphp
@include('_ep_loop',['eps'=>$fbEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ FOLDER ═══════════ --}}
{!! sec('folder',$svgFo,'Folder','Collection of file resources grouped together.') !!}
@php $foEP=[
['GET','List Files','/api/v1/activities/{id}/folder-files',true,['Admin','Instructor','Student'],'All files in a folder activity.',
 null,
 '{"data":[{"id":"ff1","activity_id":"a17","file_name":"Week1_Slides.pdf","file_path":"/storage/courses/course1/Week1_Slides.pdf","file_size":2048576,"mime_type":"application/pdf","sort_order":0},{"id":"ff2","file_name":"Exercise_Sheet.docx","file_size":45000,"sort_order":1}],"activity_id":"a17"}',
 [['200','OK','Files returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Add File','/api/v1/activities/{id}/folder-files',true,['Admin','Instructor'],'Add a file reference to a folder.',
 '{"file_name":"Supplementary_Reading.pdf","file_path":"/storage/courses/course1/Supplementary_Reading.pdf","file_size":1536000,"mime_type":"application/pdf"}',
 '{"message":"File added to folder.","data":{"id":"ff3","activity_id":"a17","file_name":"Supplementary_Reading.pdf","sort_order":2}}',
 [['201','Created','File added'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['DELETE','Remove File','/api/v1/folder-files/{id}',true,['Admin','Instructor'],'Remove a file from a folder.',
 null,
 '{"message":"File removed from folder."}',
 [['200','OK','Removed'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$foEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ FORUM ════════════ --}}
{!! sec('forum',$svgFr,'Forum','Discussion forum with threaded posts, pinning, and locking.') !!}
@php $frEP=[
['GET','List Discussions','/api/v1/activities/{id}/discussions',true,['Admin','Instructor','Student'],'All discussion threads, pinned first, then by latest update.',
 null,
 '{"data":[{"id":"disc1","activity_id":"a18","course_id":"course1","user_id":"user1","user":{"id":"user1","name":"Dr. Sarah Johnson"},"title":"Welcome & Introductions","pinned":true,"locked":false,"post_count":15,"posts_count":15},{"id":"disc2","user_id":"p1","user":{"id":"p1","name":"Alice Thompson"},"title":"Help with list comprehensions","pinned":false,"locked":false,"posts_count":8}],"activity_id":"a18"}',
 [['200','OK','Discussions returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Start Discussion','/api/v1/activities/{id}/discussions',true,['Admin','Instructor','Student'],'Create a new discussion thread with an initial post.',
 '{"title":"Best practices for error handling?","content":"I wanted to discuss different approaches to error handling in Python...","pinned":false}',
 '{"message":"Discussion created.","data":{"id":"disc3","activity_id":"a18","title":"Best practices for error handling?","user_id":"p4","post_count":1}}',
 [['201','Created','Discussion created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','List Posts','/api/v1/discussions/{id}/posts',true,['Admin','Instructor','Student'],'All posts in a discussion thread, chronological.',
 null,
 '{"data":[{"id":"fp1","discussion_id":"disc1","user_id":"user1","user":{"id":"user1","name":"Dr. Sarah Johnson"},"subject":"Welcome & Introductions","content":"Welcome to the Python course! Please introduce yourselves.","parent_id":null,"created_at":"2026-01-15T10:00:00Z"},{"id":"fp2","user_id":"p1","user":{"id":"p1","name":"Alice Thompson"},"content":"Hi everyone! Excited to learn Python.","parent_id":"fp1","created_at":"2026-01-15T10:15:00Z"}],"discussion_id":"disc1"}',
 [['200','OK','Posts returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Reply','/api/v1/discussions/{id}/posts',true,['Admin','Instructor','Student'],'Post a reply to a discussion. Optional parent_id for nested replies.',
 '{"content":"Try using try/except blocks with specific exception types.","parent_id":"fp1"}',
 '{"message":"Reply posted.","data":{"id":"fp5","discussion_id":"disc1","user_id":"p4","content":"Try using try/except blocks...","parent_id":"fp1"}}',
 [['201','Created','Reply posted'],['401','Unauthorized'],['403','Forbidden','Discussion is locked'],['422','Unprocessable Entity']]],

['PATCH','Toggle Lock','/api/v1/discussions/{id}/lock',true,['Admin','Instructor'],'Lock or unlock a discussion to prevent/allow further posts.',
 null,
 '{"message":"Discussion locked.","data":{"id":"disc1","locked":true}}',
 [['200','OK','Toggled'],['401','Unauthorized'],['404','Not Found']]],

['PATCH','Toggle Pin','/api/v1/discussions/{id}/pin',true,['Admin','Instructor'],'Pin or unpin a discussion to the top of the list.',
 null,
 '{"message":"Discussion pinned.","data":{"id":"disc1","pinned":true}}',
 [['200','OK','Toggled'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$frEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ GLOSSARY ═════════ --}}
{!! sec('glossary',$svgGl,'Glossary','Collaborative glossary of terms with approval workflow.') !!}
@php $glEP=[
['GET','List Entries','/api/v1/activities/{id}/glossary-entries',true,['Admin','Instructor','Student'],'All glossary entries sorted alphabetically by concept.',
 null,
 '{"data":[{"id":"ge1","activity_id":"a19","user_id":"p1","user":{"id":"p1","name":"Alice Thompson"},"concept":"Algorithm","definition":"A step-by-step procedure for solving a problem or accomplishing a task.","aliases":"algo","approved":true,"approved_by":"user1"},{"id":"ge2","concept":"Variable","definition":"A named storage location in memory.","approved":false}],"activity_id":"a19"}',
 [['200','OK','Entries returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Entry','/api/v1/activities/{id}/glossary-entries',true,['Admin','Instructor','Student'],'Add a new glossary term. Pending approval by default.',
 '{"concept":"Recursion","definition":"A technique where a function calls itself to solve smaller instances of the same problem.","aliases":"recursive function"}',
 '{"message":"Entry created.","data":{"id":"ge3","activity_id":"a19","concept":"Recursion","approved":false}}',
 [['201','Created','Entry created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['PUT','Update Entry','/api/v1/glossary-entries/{id}',true,['Admin','Instructor','Student'],'Edit a glossary entry.',
 '{"concept":"Algorithm","definition":"A finite sequence of well-defined instructions for solving a class of problems."}',
 '{"message":"Entry updated.","data":{"id":"ge1","concept":"Algorithm"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['PATCH','Approve Entry','/api/v1/glossary-entries/{id}/approve',true,['Admin','Instructor'],'Approve a student-submitted glossary entry.',
 null,
 '{"message":"Entry approved.","data":{"id":"ge2","approved":true,"approved_by":"user1"}}',
 [['200','OK','Approved'],['401','Unauthorized'],['404','Not Found']]],

['DELETE','Delete Entry','/api/v1/glossary-entries/{id}',true,['Admin','Instructor'],'Remove a glossary entry.',
 null,
 '{"message":"Entry deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$glEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ LESSON ═══════════ --}}
{!! sec('lesson',$svgLe,'Lesson','Branching lesson with content pages, question pages, and navigation jumps.') !!}
@php $leEP=[
['GET','List Pages','/api/v1/activities/{id}/lesson-pages',true,['Admin','Instructor','Student'],'All pages in a lesson ordered by sort_order.',
 null,
 '{"data":[{"id":"lp1","activity_id":"a20","title":"Introduction","content":"<p>Welcome to this lesson on Python functions...</p>","page_type":"content","sort_order":0,"jumps":null},{"id":"lp2","title":"What is a function?","content":"<p>A function is a reusable block of code...</p>","page_type":"question","sort_order":1,"jumps":[{"answer":"def","jump_to":"lp3"},{"answer":"var","jump_to":"lp1"}]},{"id":"lp3","title":"Summary","page_type":"end","sort_order":2}],"activity_id":"a20"}',
 [['200','OK','Pages returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Create Page','/api/v1/activities/{id}/lesson-pages',true,['Admin','Instructor'],'Add a new page. Types: content | question | branch | end.',
 '{"title":"Practice Exercise","content":"<p>Write a function that calculates factorial...</p>","page_type":"content","jumps":null}',
 '{"message":"Page created.","data":{"id":"lp4","activity_id":"a20","title":"Practice Exercise","page_type":"content","sort_order":3}}',
 [['201','Created','Page created'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['PUT','Update Page','/api/v1/lesson-pages/{id}',true,['Admin','Instructor'],'Edit a lesson page content, title, or jump logic.',
 '{"title":"Introduction to Functions","content":"<p>Updated content...</p>","jumps":[{"answer":"correct","jump_to":"lp3"}]}',
 '{"message":"Page updated.","data":{"id":"lp1","title":"Introduction to Functions"}}',
 [['200','OK','Updated'],['401','Unauthorized'],['404','Not Found'],['422','Unprocessable Entity']]],

['DELETE','Delete Page','/api/v1/lesson-pages/{id}',true,['Admin','Instructor'],'Remove a lesson page.',
 null,
 '{"message":"Page deleted."}',
 [['200','OK','Deleted'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$leEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ SCORM ════════════ --}}
{!! sec('scorm',$svgSc,'SCORM','SCORM 1.2/2004 package tracking with per-student attempt data.') !!}
@php $scEP=[
['GET','List Tracks','/api/v1/activities/{id}/scorm-tracks',true,['Admin','Instructor','Student'],'SCORM tracking records. Filter by ?student_id= for a specific learner.',
 null,
 '{"data":[{"id":"st1","activity_id":"a21","student_id":"p1","student":{"id":"p1","name":"Alice Thompson"},"attempt":1,"element":"cmi.core.lesson_status","value":"completed","status":"completed","score_raw":92,"score_max":100,"total_time":"00:45:30"},{"id":"st2","student_id":"p4","attempt":1,"element":"cmi.core.lesson_status","value":"incomplete","status":"incomplete","score_raw":35,"score_max":100}],"activity_id":"a21"}',
 [['200','OK','Tracks returned'],['401','Unauthorized'],['404','Not Found']]],

['POST','Record Track','/api/v1/activities/{id}/scorm-tracks',true,['Student'],'Record or update a SCORM tracking element for the authenticated student.',
 '{"element":"cmi.core.lesson_status","value":"completed","attempt":1,"status":"completed","score_raw":92,"score_max":100,"total_time":"00:45:30"}',
 '{"message":"Track recorded.","data":{"id":"st3","activity_id":"a21","student_id":"p1","element":"cmi.core.lesson_status","value":"completed","attempt":1}}',
 [['200','OK','Track recorded'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Progress Summary','/api/v1/activities/{id}/scorm-tracks/summary',true,['Student'],'Summary of SCORM progress for the authenticated student.',
 null,
 '{"activity_id":"a21","student_id":"p1","total_attempts":1,"latest_status":"completed","latest_score":92,"score_max":100}',
 [['200','OK','Summary returned'],['401','Unauthorized'],['404','Not Found']]],
]; @endphp
@include('_ep_loop',['eps'=>$scEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ AI INSIGHTS ══════ --}}
{!! sec('ai',$svgA,'AI Insights','GPT-powered analytics: performance, skill metrics, at-risk detection, recommendations, and question generation.') !!}
@php $aiEP=[
['GET','Performance Snapshots','/api/v1/courses/{id}/ai/performance',true,['Instructor','Admin'],'Weekly KPI trends: avg grade, completion rate, engagement score.',
 null,
 '{"data":[{"id":"ps1","course_id":"course1","week_label":"W1","avg_grade":72,"completion_rate":88,"engagement_score":91,"recorded_at":"2026-01-19"},{"id":"ps5","week_label":"W5","avg_grade":79,"completion_rate":91,"engagement_score":93}],"course_id":"course1"}',
 [['200','OK','Snapshots returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','Skill Metrics','/api/v1/courses/{id}/ai/skills',true,['Instructor','Admin'],'Radar chart data for 6 skill dimensions.',
 null,
 '{"course_id":"course1","data":[{"id":"sm1","skill_label":"Quiz Performance","score":78,"full_mark":100},{"id":"sm2","skill_label":"Assignment Quality","score":82},{"id":"sm3","skill_label":"Forum Participation","score":65},{"id":"sm4","skill_label":"Completion Rate","score":89},{"id":"sm5","skill_label":"Timeliness","score":72},{"id":"sm6","skill_label":"Peer Collaboration","score":58}]}',
 [['200','OK','Metrics returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','At-Risk Students','/api/v1/courses/{id}/ai/at-risk',true,['Instructor','Admin'],'GPT-flagged students with risk level (high/medium/low), missed activities, grade.',
 null,
 '{"data":[{"id":"ar1","student_id":"p4","student_name":"David Kim","progress":23,"last_access":"5 days ago","missed_activities":4,"grade":32,"risk_level":"high","ai_recommendation":"Schedule an immediate intervention meeting."},{"id":"ar3","student_name":"Frank Lee","progress":45,"risk_level":"medium","ai_recommendation":"Send an engagement reminder with resources."}],"course_id":"course1"}',
 [['200','OK','At-risk list returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','AI Suggestions','/api/v1/courses/{id}/ai/recommendations',true,['Instructor','Admin'],'Pedagogical recommendations by GPT (impact: high/medium/urgent/low).',
 null,
 '{"data":[{"id":"rec1","title":"Increase Quiz Frequency","description":"Students with weekly quizzes show 23% better retention.","impact_level":"high","icon_name":"Zap","color_scheme":"purple"},{"id":"rec4","title":"Send Engagement Reminders","description":"4 students haven\'t accessed in 3+ days. Reminders reduce dropout by 40%.","impact_level":"urgent","color_scheme":"red"}],"course_id":"course1"}',
 [['200','OK','Recommendations returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','Content Recommendations','/api/v1/courses/{id}/ai/content',true,['Instructor','Admin'],'AI-surfaced external/internal resources for detected gaps.',
 null,
 '{"data":[{"id":"cr1","title":"Python Classes Deep Dive","content_type":"Video","relevance_score":98,"source":"YouTube","url":"https://youtube.com"},{"id":"cr2","title":"Object-Oriented Programming in Python","content_type":"Article","relevance_score":94,"source":"Real Python"}],"course_id":"course1"}',
 [['200','OK','Recommendations returned'],['401','Unauthorized'],['403','Forbidden']]],

['POST','Generate Questions','/api/v1/courses/{id}/ai/generate-questions',true,['Instructor','Admin'],'Trigger GPT to generate quiz questions.',
 '{"topic":"Object-Oriented Programming","difficulty":"Medium","count":4,"type":"multiple_choice"}',
 '{"message":"Question generation queued.","course_id":"course1"}',
 [['200','OK','Generation queued'],['401','Unauthorized'],['403','Forbidden'],['422','Unprocessable Entity']]],

['GET','Generated Questions','/api/v1/courses/{id}/ai/generated-questions',true,['Instructor','Admin'],'List AI-generated questions filtered by status.',
 null,
 '{"data":[{"id":"gq1","topic":"Object-Oriented Programming","question_text":"What is the difference between a class and an object in Python?","question_type":"Essay","difficulty":"Medium","status":"generated","generated_at":"2026-04-13T10:00:00Z"},{"id":"gq2","question_type":"Multiple Choice","difficulty":"Easy","status":"generated"}],"course_id":"course1","status_filter":"generated"}',
 [['200','OK','Questions returned'],['401','Unauthorized'],['403','Forbidden']]],

['PATCH','Update Question Status','/api/v1/ai/generated-questions/{id}',true,['Instructor','Admin'],'Accept (added_to_bank) or dismiss an AI-generated question.',
 '{"status":"added_to_bank"}',
 '{"message":"Question status updated.","id":"gq1","status":"added_to_bank"}',
 [['200','OK','Status updated'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found'],['422','Unprocessable Entity','Status must be added_to_bank or dismissed']]],

['GET','Activity Performance','/api/v1/courses/{id}/ai/activity-performance',true,['Instructor','Admin'],'Average score % per activity (horizontal bar chart).',
 null,
 '{"data":[{"id":"ap1","activity_name":"Quiz 1","avg_score_percentage":85,"grade_max":100},{"id":"ap2","activity_name":"Assignment 1","avg_score_percentage":78,"grade_max":50},{"id":"ap3","activity_name":"Quiz 2","avg_score_percentage":71},{"id":"ap4","activity_name":"Forum","avg_score_percentage":90}],"course_id":"course1"}',
 [['200','OK','Data returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','Weekly Engagement','/api/v1/courses/{id}/ai/engagement',true,['Instructor','Admin'],'Daily active students and submission counts for the engagement chart.',
 null,
 '{"data":[{"id":"de1","day_label":"Mon","active_students":120,"submissions":45,"week_of":"2026-04-07"},{"id":"de2","day_label":"Tue","active_students":138,"submissions":60},{"id":"de4","day_label":"Thu","active_students":156,"submissions":72},{"id":"de6","day_label":"Sat","active_students":89,"submissions":30}],"course_id":"course1"}',
 [['200','OK','Engagement data returned'],['401','Unauthorized'],['403','Forbidden']]],
]; @endphp
@include('_ep_loop',['eps'=>$aiEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ PIPELINE ══════════ --}}
{!! sec('pipeline',$svgP,'Learner Analytics Pipeline','Full L0→L1→L2→L3→RE→IE→FL pipeline: HATC profiles, signals, risk scoring, interventions, and feedback loop.') !!}
@php $pipeEP=[
['GET','Learner Profile','/api/v1/courses/{id}/learners/{userId}/profile',true,['Instructor','Admin'],'L0 declared HATC profile, LMS flags, and drift status.',
 null,
 '{"data":{"id":"lp4","learner_id":"p4","course_id":"course1","primary_profile":"C","secondary_profile":"T","h_score":7,"a_score":8,"t_score":11,"c_score":14,"declared_preferences":["structured pathway","deadline reminders"],"lms_flags":{"structured_pathway":true,"deadline_reminders":true,"peer_review":true},"drift_flag":true,"drift_weeks_count":2},"course_id":"course1","user_id":"p4"}',
 [['200','OK','Profile returned'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],

['POST','Set Learner Profile','/api/v1/courses/{id}/learners/{userId}/profile',true,['Instructor','Admin','Student'],'Create or update HATC profile scores and LMS flags.',
 '{"profile_type":"H","h_score":14,"a_score":5,"t_score":11,"c_score":7,"declared_preferences":["self-directed resolution","reflective processing"]}',
 '{"message":"Learner profile saved.","course_id":"course1","user_id":"p1"}',
 [['200','OK','Profile saved'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Behavioral Signals','/api/v1/courses/{id}/learners/{userId}/signals/behavioral',true,['Instructor','Admin'],'L1 weekly behavioral data (login freq, time-on-task, completion rate, quiz attempts, forum posts).',
 null,
 '{"data":{"id":"bs_p4_w7","learner_id":"p4","week_number":7,"login_frequency":2,"time_on_task_hours":1.8,"content_completion_rate":0.55,"quiz_attempt_count":2,"submission_timing":"late_3_5","forum_post_count":0,"colour_flags":{"login_frequency":"amber","time_on_task_hours":"orange","forum_post_rate":"red"}},"course_id":"course1","user_id":"p4","week":7}',
 [['200','OK','Signals returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','Cognitive Signals','/api/v1/courses/{id}/learners/{userId}/signals/cognitive',true,['Instructor','Admin'],'L2 weekly cognitive data (revisit_flag, score trends).',
 null,
 '{"data":[],"course_id":"course1","user_id":"p4","week":null}',
 [['200','OK','Signals returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','Emotional Signals','/api/v1/courses/{id}/learners/{userId}/signals/emotional',true,['Instructor','Admin'],'L3 weekly emotional data (mood_drift_flag, pulse scores, badge_earned).',
 null,
 '{"data":[],"course_id":"course1","user_id":"p4","week":null}',
 [['200','OK','Signals returned'],['401','Unauthorized'],['403','Forbidden']]],

['POST','Submit Pulse','/api/v1/courses/{id}/learners/{userId}/pulse',true,['Student'],'Record weekly pulse check-in: confidence and energy (1–5).',
 '{"week_number":7,"pulse_confidence":3,"pulse_energy":2}',
 '{"message":"Pulse check-in recorded.","course_id":"course1","user_id":"p4"}',
 [['201','Created','Pulse recorded'],['401','Unauthorized'],['422','Unprocessable Entity','Values must be 1-5']]],

['GET','Risk Score','/api/v1/courses/{id}/learners/{userId}/risk',true,['Instructor','Admin'],'RE risk score 0–100, tier (0=GREEN / 1=AMBER / 2=ORANGE / 3=RED), anomaly flag, signal breakdown.',
 null,
 '{"data":{"final_score":82,"tier":3,"anomaly_flag":true,"signal_breakdown":{"behavioral":0.667,"cognitive":0.5,"emotional":0.4}},"course_id":"course1","user_id":"p4","week":7}',
 [['200','OK','Risk score returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','All Risk Scores','/api/v1/courses/{id}/risk-scores',true,['Instructor','Admin'],'Paginated risk scores for all learners in a course.',
 null,
 '{"data":[{"learner_id":"p4","student_name":"David Kim","final_score":82,"tier":3},{"learner_id":"p8","student_name":"Henry Adams","final_score":76,"tier":3},{"learner_id":"p6","student_name":"Frank Lee","final_score":48,"tier":1}],"course_id":"course1","week":null}',
 [['200','OK','Risk scores returned'],['401','Unauthorized'],['403','Forbidden']]],

['GET','List Interventions','/api/v1/courses/{id}/interventions',true,['Instructor','Admin'],'All interventions triggered for a course.',
 null,
 '{"data":[],"course_id":"course1"}',
 [['200','OK','Interventions returned'],['401','Unauthorized'],['403','Forbidden']]],

['POST','Create Intervention','/api/v1/courses/{id}/interventions',true,['Instructor','Admin'],'Log a facilitator-initiated or automated intervention. Channels: lms_message | email | video_call | pastoral_referral.',
 '{"learner_id":"p4","week_number":7,"tier":3,"channel":"lms_message","template_id":"tier3-template-1","message_body":"Hi David, I noticed you\'ve been struggling. Let\'s set up a call."}',
 '{"message":"Intervention logged.","course_id":"course1"}',
 [['201','Created','Intervention logged'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Feedback Evaluation','/api/v1/interventions/{id}/evaluation',true,['Instructor','Admin'],'FL feedback-loop evaluation: T+7/T+14 score trajectory and outcome.',
 null,
 '{"data":null,"intervention_id":"int1"}',
 [['200','OK','Evaluation returned'],['401','Unauthorized'],['403','Forbidden'],['404','Not Found']]],

['POST','Submit Evaluation','/api/v1/interventions/{id}/evaluation',true,['Instructor','Admin'],'Record T+7/T+14 score trajectory and RE calibration. Outcomes: recovered | partial_recovery | no_change | worsened | escalated.',
 '{"evaluated_at_week":9,"score_before":82,"score_at_t7":65,"score_at_t14":48,"outcome_label":"partial_recovery","recovery_threshold_met":false,"model_notes":"Student responded to message but engagement still low."}',
 '{"message":"Evaluation submitted.","intervention_id":"int1"}',
 [['201','Created','Evaluation submitted'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Drift Logs','/api/v1/courses/{id}/learners/{userId}/drift-logs',true,['Instructor','Admin'],'Profile drift detection history for a learner.',
 null,
 '{"data":[],"course_id":"course1","user_id":"p4"}',
 [['200','OK','Drift logs returned'],['401','Unauthorized'],['403','Forbidden']]],
]; @endphp
@include('_ep_loop',['eps'=>$pipeEP,'mc'=>$mc])
{!! endsec() !!}

{{-- ═══════════════════════════════════════════ PROFILE ═══════════ --}}
{!! sec('profile',$svgU,'Profile & Preferences','User profile and notification preference toggles.') !!}
@php $profEP=[
['GET','Get Profile','/api/v1/profile',true,['Admin','Instructor','Student'],'Full profile for the authenticated user.',
 null,
 '{"data":{"id":"user1","name":"Dr. Sarah Johnson","email":"sarah.johnson@university.edu","role":"instructor","initials":"SJ","department":"Computer Science","institution":"University of Technology","country":"United States","timezone":"America/New_York","language":"English","bio":"Experienced instructor in Computer Science. PhD from MIT.","join_date":"2019-09-01","last_access":"Today","enrolled_courses":2}}',
 [['200','OK','Profile returned'],['401','Unauthorized']]],

['PUT','Update Profile','/api/v1/profile',true,['Admin','Instructor','Student'],'Update editable fields: name, bio, department, institution, country, timezone, language.',
 '{"name":"Dr. Sarah Johnson","bio":"Experienced instructor in CS. PhD from MIT. 10+ years teaching.","department":"Computer Science","institution":"University of Technology","country":"United States","timezone":"America/New_York","language":"English"}',
 '{"message":"Profile updated.","data":{"id":"user1","name":"Dr. Sarah Johnson","bio":"Experienced instructor in CS."}}',
 [['200','OK','Profile updated'],['401','Unauthorized'],['422','Unprocessable Entity']]],

['GET','Get Preferences','/api/v1/profile/preferences',true,['Admin','Instructor','Student'],'All preference toggles for the user.',
 null,
 '{"data":[{"id":"pref1","preference_key":"email_notifications","preference_label":"Email notifications","description":"Receive emails when students submit assignments","enabled":true},{"id":"pref2","preference_key":"forum_subscriptions","preference_label":"Forum subscriptions","description":"Get notified of new forum posts in my courses","enabled":true},{"id":"pref3","preference_key":"grading_reminders","preference_label":"Grading reminders","description":"Remind me of ungraded submissions after 48 hours","enabled":true},{"id":"pref4","preference_key":"ai_suggestions","preference_label":"AI suggestions","description":"Show AI-generated insights and recommendations","enabled":true}],"user_id":"user1"}',
 [['200','OK','Preferences returned'],['401','Unauthorized']]],

['PUT','Update Preferences','/api/v1/profile/preferences',true,['Admin','Instructor','Student'],'Enable/disable a preference. Keys: email_notifications | forum_subscriptions | grading_reminders | ai_suggestions.',
 '{"preference_key":"grading_reminders","preference_value":false}',
 '{"message":"Preference updated.","data":{"preference_key":"grading_reminders","preference_value":false}}',
 [['200','OK','Preference updated'],['401','Unauthorized'],['422','Unprocessable Entity','Invalid preference_key']]],
]; @endphp
@include('_ep_loop',['eps'=>$profEP,'mc'=>$mc])
{!! endsec() !!}

</div>
</main>
</div>

<script>
function toggleAccordion(btn){
  const body=btn.nextElementSibling;
  const chev=btn.querySelector('.chev');
  const isOpen=body.classList.contains('open');
  document.querySelectorAll('.acc-body.open').forEach(b=>{b.classList.remove('open');b.previousElementSibling.querySelector('.chev')?.classList.remove('open');});
  if(!isOpen){body.classList.add('open');chev?.classList.add('open');}
}
const obs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting){const id=e.target.id;document.querySelectorAll('.nl').forEach(l=>{l.classList.toggle('active',l.dataset.section===id);});}});
},{rootMargin:'-20% 0px -70% 0px'});
document.querySelectorAll('[id]').forEach(s=>{if(s.tagName!=='INPUT')obs.observe(s);});
document.querySelectorAll('.nl').forEach(l=>{l.addEventListener('click',()=>{document.querySelectorAll('.nl').forEach(x=>x.classList.remove('active'));l.classList.add('active');});});
</script>
</body>
</html>
