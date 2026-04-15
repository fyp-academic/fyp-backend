<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\AIInsightController;
use App\Http\Controllers\LearnerAnalyticsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChoiceController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DatabaseActivityController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\GlossaryController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ScormController;
use App\Http\Controllers\MessageController;

Route::prefix('v1')->group(function () {

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================
    Route::prefix('auth')->group(function () {

        // Public — no token required
        Route::post('register',         [AuthController::class, 'register']);
        Route::post('login',            [AuthController::class, 'login']);
        Route::post('forgot-password',  [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',   [AuthController::class, 'resetPassword']);
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed'])
            ->name('verification.verify');

        // Protected — valid Sanctum token required
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('email/resend', [AuthController::class, 'resendVerification']);
            Route::get('me',            [AuthController::class, 'me']);
            Route::post('logout',       [AuthController::class, 'logout']);
        });
    });

    // =========================================================================
    // ALL REMAINING ROUTES — require valid Sanctum token
    // =========================================================================
    Route::middleware('auth:sanctum')->group(function () {

        // ─────────────────────────────────────────────────────────────────────
        // DASHBOARDS
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('dashboard')->group(function () {
            Route::get('admin',       [DashboardController::class, 'admin']);
            Route::get('instructor',  [DashboardController::class, 'instructor']);
            Route::get('student',     [DashboardController::class, 'student']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // COURSES & ENROLLMENT
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('courses')->group(function () {
            Route::get('/',    [CourseController::class, 'index']);
            Route::post('/',   [CourseController::class, 'store']);
            Route::get('/{id}',    [CourseController::class, 'show']);
            Route::put('/{id}',    [CourseController::class, 'update']);
            Route::delete('/{id}', [CourseController::class, 'destroy']);

            // Participants / Enrollment
            Route::get('/{id}/participants',         [CourseController::class, 'participants']);
            Route::post('/{id}/enroll',              [CourseController::class, 'enroll']);
            Route::delete('/{id}/enroll/{userId}',   [CourseController::class, 'unenroll']);

            // Student self-enrollment
            Route::post('/{id}/join',   [CourseController::class, 'selfEnroll']);
            Route::delete('/{id}/leave', [CourseController::class, 'selfUnenroll']);

            // Sections (nested under course)
            Route::get('/{id}/sections',                          [SectionController::class, 'index']);
            Route::post('/{id}/sections',                         [SectionController::class, 'store']);
            Route::put('/{id}/sections/{sectionId}',              [SectionController::class, 'update']);
            Route::delete('/{id}/sections/{sectionId}',           [SectionController::class, 'destroy']);

            // Grades (nested under course)
            Route::get('/{id}/grades',                            [GradeController::class, 'index']);
            Route::get('/{id}/grades/student/{studentId}',        [GradeController::class, 'studentGrades']);

            // AI Insights (nested under course)
            Route::prefix('/{id}/ai')->group(function () {
                Route::get('performance',           [AIInsightController::class, 'performance']);
                Route::get('skills',                [AIInsightController::class, 'skills']);
                Route::get('at-risk',               [AIInsightController::class, 'atRisk']);
                Route::get('recommendations',       [AIInsightController::class, 'recommendations']);
                Route::get('content',               [AIInsightController::class, 'contentRecommendations']);
                Route::post('generate-questions',   [AIInsightController::class, 'generateQuestions']);
                Route::get('generated-questions',   [AIInsightController::class, 'generatedQuestions']);
                Route::get('activity-performance',  [AIInsightController::class, 'activityPerformance']);
                Route::get('engagement',            [AIInsightController::class, 'engagement']);
            });

            // Learner Analytics Pipeline (nested under course)
            Route::prefix('/{id}/learners/{userId}')->group(function () {
                Route::get('profile',               [LearnerAnalyticsController::class, 'profile']);
                Route::post('profile',              [LearnerAnalyticsController::class, 'setProfile']);
                Route::get('signals/behavioral',    [LearnerAnalyticsController::class, 'behavioralSignals']);
                Route::get('signals/cognitive',     [LearnerAnalyticsController::class, 'cognitiveSignals']);
                Route::get('signals/emotional',     [LearnerAnalyticsController::class, 'emotionalSignals']);
                Route::post('pulse',                [LearnerAnalyticsController::class, 'submitPulse']);
                Route::get('risk',                  [LearnerAnalyticsController::class, 'riskScore']);
                Route::get('drift-logs',            [LearnerAnalyticsController::class, 'driftLogs']);
            });

            Route::get('/{id}/risk-scores',     [LearnerAnalyticsController::class, 'allRiskScores']);
            Route::get('/{id}/interventions',   [LearnerAnalyticsController::class, 'interventions']);
            Route::post('/{id}/interventions',  [LearnerAnalyticsController::class, 'createIntervention']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // SECTIONS → ACTIVITIES  (top-level section access)
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('sections/{id}')->group(function () {
            Route::get('activities',  [ActivityController::class, 'index']);
            Route::post('activities', [ActivityController::class, 'store']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // ACTIVITIES  (standalone mutations)
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('activities')->group(function () {
            Route::put('/{id}',    [ActivityController::class, 'update']);
            Route::delete('/{id}', [ActivityController::class, 'destroy']);

            // Quiz questions nested under activity
            Route::get('/{id}/questions',  [QuizController::class, 'index']);
            Route::post('/{id}/questions', [QuizController::class, 'store']);

            // Assignment submissions
            Route::get('/{id}/submissions',  [AssignmentController::class, 'index']);
            Route::post('/{id}/submissions', [AssignmentController::class, 'store']);

            // Attendance sessions
            Route::get('/{id}/attendance-sessions',  [AttendanceController::class, 'sessions']);
            Route::post('/{id}/attendance-sessions', [AttendanceController::class, 'createSession']);

            // Book chapters
            Route::get('/{id}/chapters',  [BookController::class, 'index']);
            Route::post('/{id}/chapters', [BookController::class, 'store']);

            // Checklist items
            Route::get('/{id}/checklist-items',  [ChecklistController::class, 'index']);
            Route::post('/{id}/checklist-items', [ChecklistController::class, 'store']);

            // Choice options & responses
            Route::get('/{id}/choice-options',    [ChoiceController::class, 'options']);
            Route::post('/{id}/choice-options',   [ChoiceController::class, 'storeOption']);
            Route::post('/{id}/choice-responses', [ChoiceController::class, 'respond']);
            Route::get('/{id}/choice-results',    [ChoiceController::class, 'results']);

            // Certificate
            Route::get('/{id}/certificate',          [CertificateController::class, 'show']);
            Route::post('/{id}/certificate',         [CertificateController::class, 'upsert']);
            Route::get('/{id}/certificate/issues',   [CertificateController::class, 'issues']);
            Route::post('/{id}/certificate/issue',   [CertificateController::class, 'issue']);

            // Database activity (fields & entries)
            Route::get('/{id}/db-fields',   [DatabaseActivityController::class, 'fields']);
            Route::post('/{id}/db-fields',  [DatabaseActivityController::class, 'storeField']);
            Route::get('/{id}/db-entries',  [DatabaseActivityController::class, 'entries']);
            Route::post('/{id}/db-entries', [DatabaseActivityController::class, 'storeEntry']);

            // Feedback questions & responses
            Route::get('/{id}/feedback-questions',   [FeedbackController::class, 'questions']);
            Route::post('/{id}/feedback-questions',  [FeedbackController::class, 'storeQuestion']);
            Route::get('/{id}/feedback-responses',   [FeedbackController::class, 'responses']);
            Route::post('/{id}/feedback-responses',  [FeedbackController::class, 'submitResponses']);

            // Folder files
            Route::get('/{id}/folder-files',  [FolderController::class, 'index']);
            Route::post('/{id}/folder-files', [FolderController::class, 'store']);

            // Forum discussions
            Route::get('/{id}/discussions',  [ForumController::class, 'discussions']);
            Route::post('/{id}/discussions', [ForumController::class, 'createDiscussion']);

            // Glossary entries
            Route::get('/{id}/glossary-entries',  [GlossaryController::class, 'index']);
            Route::post('/{id}/glossary-entries', [GlossaryController::class, 'store']);

            // Lesson pages
            Route::get('/{id}/lesson-pages',  [LessonController::class, 'index']);
            Route::post('/{id}/lesson-pages', [LessonController::class, 'store']);

            // SCORM tracks
            Route::get('/{id}/scorm-tracks',          [ScormController::class, 'index']);
            Route::post('/{id}/scorm-tracks',         [ScormController::class, 'store']);
            Route::get('/{id}/scorm-tracks/summary',  [ScormController::class, 'summary']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // GRADES — standalone grade-item access
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('grade-items')->group(function () {
            Route::get('/{id}',         [GradeController::class, 'show']);
            Route::post('/{id}/grades', [GradeController::class, 'submitGrade']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // CATEGORIES
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('categories')->group(function () {
            Route::get('/',    [CategoryController::class, 'index']);
            Route::post('/',   [CategoryController::class, 'store']);
            Route::put('/{id}',    [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // NOTIFICATIONS
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/',                     [NotificationController::class, 'index']);
            Route::post('mark-all-read',        [NotificationController::class, 'markAllRead']);
            Route::patch('/{id}/read',          [NotificationController::class, 'markRead']);
            Route::delete('/{id}',              [NotificationController::class, 'destroy']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // MESSAGING — conversations & messages (real-time via Reverb)
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('conversations')->group(function () {
            Route::get('/',    [ConversationController::class, 'index']);
            Route::post('/',   [ConversationController::class, 'store']);
            Route::get('/{id}/messages',  [MessageController::class, 'index']);
            Route::post('/{id}/messages', [MessageController::class, 'store']);   // supports file upload
            Route::patch('/{id}/messages/read', [MessageController::class, 'markRead']);
        });
        Route::post('messages/{id}/react', [MessageController::class, 'react']); // emoji toggle

        // ─────────────────────────────────────────────────────────────────────
        // QUIZ — standalone question & answer mutations
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('questions')->group(function () {
            Route::put('/{id}',    [QuizController::class, 'updateQuestion']);
            Route::delete('/{id}', [QuizController::class, 'destroyQuestion']);
            Route::get('/{id}/answers',  [QuizController::class, 'answers']);
            Route::post('/{id}/answers', [QuizController::class, 'storeAnswer']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // AI — generated question status (standalone)
        // ─────────────────────────────────────────────────────────────────────
        Route::patch('ai/generated-questions/{id}', [AIInsightController::class, 'updateQuestionStatus']);

        // ─────────────────────────────────────────────────────────────────────
        // INTERVENTIONS — feedback evaluations (standalone)
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('interventions')->group(function () {
            Route::get('/{id}/evaluation',  [LearnerAnalyticsController::class, 'feedbackEvaluation']);
            Route::post('/{id}/evaluation', [LearnerAnalyticsController::class, 'submitEvaluation']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // MOODLE TOOLS — standalone sub-resource mutations
        // ─────────────────────────────────────────────────────────────────────

        // Assignment submissions
        Route::get('submissions/{id}',       [AssignmentController::class, 'show']);
        Route::put('submissions/{id}/grade', [AssignmentController::class, 'grade']);

        // Attendance logs
        Route::prefix('attendance-sessions/{id}')->group(function () {
            Route::get('logs',       [AttendanceController::class, 'logs']);
            Route::post('logs',      [AttendanceController::class, 'recordAttendance']);
            Route::post('logs/bulk', [AttendanceController::class, 'bulkRecord']);
        });

        // Book chapters
        Route::put('chapters/{id}',    [BookController::class, 'update']);
        Route::delete('chapters/{id}', [BookController::class, 'destroy']);

        // Checklist items
        Route::put('checklist-items/{id}',    [ChecklistController::class, 'update']);
        Route::delete('checklist-items/{id}', [ChecklistController::class, 'destroy']);

        // Database fields & entries
        Route::delete('db-fields/{id}',         [DatabaseActivityController::class, 'destroyField']);
        Route::patch('db-entries/{id}/approve',  [DatabaseActivityController::class, 'approveEntry']);
        Route::delete('db-entries/{id}',         [DatabaseActivityController::class, 'destroyEntry']);

        // Feedback questions
        Route::delete('feedback-questions/{id}', [FeedbackController::class, 'destroyQuestion']);

        // Folder files
        Route::delete('folder-files/{id}', [FolderController::class, 'destroy']);

        // Forum discussions & posts
        Route::prefix('discussions/{id}')->group(function () {
            Route::get('posts',   [ForumController::class, 'posts']);
            Route::post('posts',  [ForumController::class, 'reply']);
            Route::patch('lock',  [ForumController::class, 'toggleLock']);
            Route::patch('pin',   [ForumController::class, 'togglePin']);
        });

        // Glossary entries
        Route::put('glossary-entries/{id}',            [GlossaryController::class, 'update']);
        Route::patch('glossary-entries/{id}/approve',  [GlossaryController::class, 'approve']);
        Route::delete('glossary-entries/{id}',         [GlossaryController::class, 'destroy']);

        // Lesson pages
        Route::put('lesson-pages/{id}',    [LessonController::class, 'update']);
        Route::delete('lesson-pages/{id}', [LessonController::class, 'destroy']);

        // ─────────────────────────────────────────────────────────────────────
        // PROFILE & PREFERENCES
        // ─────────────────────────────────────────────────────────────────────
        Route::prefix('profile')->group(function () {
            Route::get('/',            [ProfileController::class, 'show']);
            Route::put('/',            [ProfileController::class, 'update']);
            Route::get('preferences',  [ProfileController::class, 'preferences']);
            Route::put('preferences',  [ProfileController::class, 'updatePreferences']);
        });
    });
});
