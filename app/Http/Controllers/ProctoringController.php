<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Activity;
use App\Models\PracticalSubmission;
use App\Models\ProctoringSession;
use App\Models\ProctoringViolation;
use App\Models\QuizAttempt;
use App\Services\ActivityResultService;
use Illuminate\Support\Str;
use App\Services\GeminiService;
use Smalot\PdfParser\Parser as PdfParser;

class ProctoringController extends Controller
{
    private const AUTO_SUBMIT_THRESHOLD = 5;

    public function __construct(private GeminiService $gemini) {}

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/proctoring/start
    // ─────────────────────────────────────────────────────────────────────
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'activity_id'           => 'required|string',
            'course_id'             => 'nullable|string',
            'context_type'          => 'sometimes|string|in:quiz,assignment,practical',
            'quiz_attempt_id'       => 'nullable|string',
            'auto_submit_threshold' => 'sometimes|integer|min:1|max:20',
        ]);

        $user = Auth::user();

        $session = ProctoringSession::create([
            'student_id'            => $user->id,
            'activity_id'           => $request->activity_id,
            'course_id'             => $request->course_id,
            'context_type'          => $request->input('context_type', 'quiz'),
            'quiz_attempt_id'       => $request->quiz_attempt_id,
            'status'                => 'active',
            'violation_count'       => 0,
            'auto_submit_threshold' => $request->input('auto_submit_threshold', self::AUTO_SUBMIT_THRESHOLD),
            'is_flagged'            => false,
            'started_at'            => now(),
        ]);

        return response()->json(['session_id' => $session->id], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/proctoring/violation
    // ─────────────────────────────────────────────────────────────────────
    public function violation(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string|exists:proctoring_sessions,id',
            'type'       => 'required|string',
            'metadata'   => 'nullable|array',
            'snapshot'   => 'nullable|string',   // base64 JPEG frame
        ]);

        $session = ProctoringSession::findOrFail($request->session_id);

        if ($session->status !== 'active') {
            return response()->json(['warning_count' => $session->violation_count, 'action' => 'ended'], 200);
        }

        $session->increment('violation_count');
        $session->refresh();
        $count     = $session->violation_count;
        $threshold = $session->auto_submit_threshold ?: self::AUTO_SUBMIT_THRESHOLD;

        $action = match (true) {
            $count >= $threshold     => 'force_submit',
            $count === $threshold - 1 => 'final_warning',
            default => 'warn',
        };

        if ($action === 'force_submit') {
            $session->update(['status' => 'force_submitted', 'is_flagged' => true, 'ended_at' => now()]);
            $this->forceSubmitAttempt($session);
        } elseif ($count >= 3) {
            $session->update(['is_flagged' => true]);
        }

        $snapshotUrl = null;
        if ($request->filled('snapshot')) {
            $snapshotUrl = $this->storeSnapshot($session->id, $request->snapshot);
        }

        ProctoringViolation::create([
            'session_id'           => $session->id,
            'type'                 => $request->type,
            'metadata'             => $request->input('metadata'),
            'action_taken'         => $action,
            'warning_count_at_time'=> $count,
            'occurred_at'          => now(),
            'snapshot_url'         => $snapshotUrl,
        ]);

        return response()->json([
            'warning_count' => $count,
            'action'        => $action,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/proctoring/webcam-check
    // ─────────────────────────────────────────────────────────────────────
    public function webcamCheck(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string|exists:proctoring_sessions,id',
            'image'      => 'required|string',
        ]);

        $session = ProctoringSession::findOrFail($request->session_id);

        if ($session->status !== 'active') {
            return response()->json(['ok' => false, 'violation' => null]);
        }

        try {
            $analysis = $this->gemini->analyzeWebcamFrame($request->image);
        } catch (\Throwable $e) {
            Log::warning('Proctoring: webcam analysis failed', ['error' => $e->getMessage()]);
            return response()->json(['ok' => true, 'violation' => null]);
        }

        $violation = $analysis['violation'] ?? null;

        if ($violation) {
            $violationRequest = new Request([
                'session_id' => $session->id,
                'type'       => $violation,
                'metadata'   => ['source' => 'webcam', 'analysis' => $analysis],
                'snapshot'   => $request->image,  // store the frame that triggered the violation
            ]);
            $violationResponse = $this->violation($violationRequest);
            $violationData     = $violationResponse->getData(true);

            return response()->json([
                'ok'            => false,
                'violation'     => $violation,
                'warning_count' => $violationData['warning_count'],
                'action'        => $violationData['action'],
            ]);
        }

        return response()->json(['ok' => true, 'violation' => null]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/proctoring/end
    // ─────────────────────────────────────────────────────────────────────
    public function end(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|string|exists:proctoring_sessions,id']);

        $session = ProctoringSession::findOrFail($request->session_id);
        if ($session->status === 'active') {
            $session->update(['status' => 'ended', 'ended_at' => now()]);
        }

        return response()->json(['message' => 'Session ended.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/proctoring/analyze-submission
    // For text assignments or uploaded PDFs — detect AI-generated content.
    // ─────────────────────────────────────────────────────────────────────
    public function analyzeSubmission(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'  => 'nullable|string|exists:proctoring_sessions,id',
            'text'        => 'nullable|string',
            'file'        => 'nullable|file|mimes:pdf,txt,doc,docx|max:10240',
        ]);

        $text = $request->input('text', '');

        // Extract text from uploaded PDF if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();

            if ($mime === 'application/pdf' || $file->getClientOriginalExtension() === 'pdf') {
                try {
                    $parser  = new PdfParser();
                    $pdf     = $parser->parseFile($file->getPathname());
                    $text   .= ' ' . $pdf->getText();
                } catch (\Throwable $e) {
                    Log::warning('Proctoring: PDF parse failed', ['error' => $e->getMessage()]);
                }
            } elseif (str_starts_with($mime ?? '', 'text/')) {
                $text .= ' ' . file_get_contents($file->getPathname());
            }
        }

        $text = trim($text);

        try {
            $result = $this->gemini->detectAiGeneratedContent($text);
        } catch (\Throwable $e) {
            Log::warning('Proctoring: AI content detection failed', ['error' => $e->getMessage()]);
            return response()->json(['is_ai_generated' => false, 'confidence' => 0, 'indicators' => [], 'recommendation' => 'Analysis unavailable']);
        }

        // If flagged and a session is active, log a violation automatically
        if (!empty($result['is_ai_generated']) && $request->filled('session_id')) {
            try {
                $violationRequest = new Request([
                    'session_id' => $request->session_id,
                    'type'       => 'ai_content_detected',
                    'metadata'   => [
                        'confidence' => $result['confidence'],
                        'indicators' => $result['indicators'] ?? [],
                    ],
                ]);
                $this->violation($violationRequest);
            } catch (\Throwable) {
                /* silent — don't block submission response */
            }
        }

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/proctoring/instructor/courses/{courseId}/sessions
    // ─────────────────────────────────────────────────────────────────────
    public function instructorSessions(Request $request, string $courseId): JsonResponse
    {
        $query = ProctoringSession::with(['student:id,name,email,profile_image', 'activity:id,name,type'])
            ->where('course_id', $courseId)
            ->orderByDesc('started_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('flagged_only')) {
            $query->where('is_flagged', true);
        }

        $paginated = $query->paginate(30);

        $paginated->getCollection()->transform(function ($s) {
            $img = $s->student?->profile_image ? url('storage/' . $s->student->profile_image) : null;
            return [
                'id'               => $s->id,
                'student'          => $s->student ? ['id' => $s->student->id, 'name' => $s->student->name, 'email' => $s->student->email, 'profile_image_url' => $img] : null,
                'activity_name'    => optional($s->activity)->name,
                'activity_type'    => optional($s->activity)->type,
                'context_type'     => $s->context_type,
                'status'           => $s->status,
                'violation_count'  => $s->violation_count,
                'is_flagged'       => $s->is_flagged,
                'started_at'       => $s->started_at,
                'ended_at'         => $s->ended_at,
                'duration_seconds' => $s->started_at && $s->ended_at ? $s->started_at->diffInSeconds($s->ended_at) : null,
            ];
        });

        $summary = [
            'total'           => ProctoringSession::where('course_id', $courseId)->count(),
            'flagged'         => ProctoringSession::where('course_id', $courseId)->where('is_flagged', true)->count(),
            'force_submitted' => ProctoringSession::where('course_id', $courseId)->where('status', 'force_submitted')->count(),
            'avg_violations'  => round((float) ProctoringSession::where('course_id', $courseId)->avg('violation_count'), 1),
        ];

        return response()->json(['summary' => $summary, 'sessions' => $paginated]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/proctoring/instructor/sessions/{sessionId}
    // ─────────────────────────────────────────────────────────────────────
    public function instructorSessionDetail(string $sessionId): JsonResponse
    {
        $session = ProctoringSession::with([
            'student:id,name,email,profile_image',
            'activity:id,name,type',
            'violations' => fn ($q) => $q->orderBy('occurred_at'),
        ])->findOrFail($sessionId);

        $imgUrl = $session->student?->profile_image ? url('storage/' . $session->student->profile_image) : null;

        return response()->json([
            'id'              => $session->id,
            'student'         => $session->student ? array_merge($session->student->only(['id','name','email']), ['profile_image_url' => $imgUrl]) : null,
            'activity_name'   => optional($session->activity)->name,
            'activity_type'   => optional($session->activity)->type,
            'context_type'    => $session->context_type,
            'status'          => $session->status,
            'violation_count' => $session->violation_count,
            'is_flagged'      => $session->is_flagged,
            'started_at'      => $session->started_at,
            'ended_at'        => $session->ended_at,
            'violations'      => $session->violations->map(fn ($v) => [
                'id'                    => $v->id,
                'type'                  => $v->type,
                'metadata'              => $v->metadata,
                'action_taken'          => $v->action_taken,
                'warning_count_at_time' => $v->warning_count_at_time,
                'occurred_at'           => $v->occurred_at,
                'snapshot_url'          => $v->snapshot_url,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helper: save a base64 JPEG to public storage and return its URL
    // ─────────────────────────────────────────────────────────────────────
    private function storeSnapshot(string $sessionId, string $base64Image): ?string
    {
        try {
            $dir      = "proctoring/{$sessionId}";
            $filename = uniqid('snap_', true) . '.jpg';
            $bytes    = base64_decode($base64Image, strict: true);
            if ($bytes === false || strlen($bytes) < 100) return null;
            Storage::disk('public')->put("{$dir}/{$filename}", $bytes);
            return Storage::disk('public')->url("{$dir}/{$filename}");
        } catch (\Throwable $e) {
            Log::warning('Proctoring: snapshot store failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helper: auto-submit quiz attempt when force_submit threshold hit
    // ─────────────────────────────────────────────────────────────────────
    private function forceSubmitAttempt(ProctoringSession $session): void
    {
        try {
            if ($session->context_type === 'quiz' && $session->quiz_attempt_id) {
                $attempt = QuizAttempt::find($session->quiz_attempt_id);
                if ($attempt && $attempt->status === 'in_progress') {
                    $attempt->update([
                        'status'       => 'submitted',
                        'submitted_at' => now(),
                    ]);
                    Log::info('Proctoring: force-submitted quiz attempt', ['attempt_id' => $attempt->id]);
                }
            } elseif ($session->context_type === 'practical') {
                // Server-side safety net: finalize the student's current code as a
                // submission even if the browser is closed/compromised. Create the
                // row when none exists yet (untimed practical whose autosave never
                // fired) so a violation auto-submit is never lost.
                $activity = Activity::find($session->activity_id);
                $sub = PracticalSubmission::firstOrNew([
                    'activity_id' => $session->activity_id,
                    'student_id'  => $session->student_id,
                ]);
                if (! $sub->exists) {
                    $sub->id         = (string) Str::uuid();
                    // Use the activity's real course, not the proctoring session's —
                    // the session can carry a different/wrong course, which would hide
                    // the row from the instructor's course-scoped view.
                    $sub->course_id  = $activity->course_id ?? $session->course_id;
                    $sub->files      = ['html' => '', 'css' => '', 'js' => ''];
                    $sub->started_at = $sub->started_at ?? now();
                }
                if ($sub->status !== 'submitted') {
                    $sub->status         = 'submitted';
                    $sub->submitted_at   = now();
                    $sub->auto_submitted = true;
                    $sub->save();
                    if ($activity) {
                        app(ActivityResultService::class)->recordCompletion($activity, $session->student_id);
                    }
                    Log::info('Proctoring: force-submitted practical', ['submission_id' => $sub->id]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Proctoring: failed to force-submit attempt', ['error' => $e->getMessage()]);
        }
    }
}
