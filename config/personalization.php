<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Warm-up gate
    |--------------------------------------------------------------------------
    |
    | Personalization (content rewrite, player switching, adaptive navigation)
    | stays inactive until a student has genuinely spent time on a course. This
    | keeps the AI honest: it does not fabricate a learner profile from day-one
    | defaults. A student becomes "ready" only after `warmup_days` have passed
    | since enrolment AND there is real activity evidence on that course
    | (at least `min_quiz_attempts` graded attempts OR `min_activity_completions`
    | completed activities).
    |
    */

    'warmup_days' => (int) env('PERSONALIZATION_WARMUP_DAYS', 7),

    'min_quiz_attempts' => (int) env('PERSONALIZATION_MIN_QUIZ_ATTEMPTS', 1),

    'min_activity_completions' => (int) env('PERSONALIZATION_MIN_ACTIVITY_COMPLETIONS', 3),
];
