<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Engagement measurement thresholds
    |--------------------------------------------------------------------------
    |
    | Centralised, tunable thresholds for the engagement engine. These used to
    | be hardcoded constants scattered across services/controllers. Defaults
    | preserve the previous behaviour.
    |
    */

    // A login session shorter than this (seconds) is treated as a "bounce".
    'bounce_seconds' => (int) env('ENGAGEMENT_BOUNCE_SECONDS', 120),

    // A quiz scored below this percentage counts as a "failure" (frustration index).
    'failure_pct' => (float) env('ENGAGEMENT_FAILURE_PCT', 50),

    // Client-side: seconds with no input before the active-time timer pauses.
    'idle_seconds' => (int) env('ENGAGEMENT_IDLE_SECONDS', 60),

    // Risk classification thresholds (final engagement score 0-100).
    'risk_engaged' => (float) env('ENGAGEMENT_RISK_ENGAGED', 70),
    'risk_at_risk' => (float) env('ENGAGEMENT_RISK_AT_RISK', 40),

    // Inactivity (days since last login) escalation thresholds.
    'inactivity_warn'   => (int) env('ENGAGEMENT_INACTIVITY_WARN', 5),
    'inactivity_danger' => (int) env('ENGAGEMENT_INACTIVITY_DANGER', 10),

    // Auto-close login sessions left open (no ended_at) longer than this many
    // minutes — abandoned tabs that never sent a close/beacon.
    'stale_session_minutes' => (int) env('ENGAGEMENT_STALE_SESSION_MINUTES', 30),

];
