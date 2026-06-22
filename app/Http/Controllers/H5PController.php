<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ContentChunk;
use App\Models\CourseMaterial;
use App\Models\User;
use App\Services\ActivityMaterialService;
use App\Services\ActivityResultService;
use App\Services\H5P\H5PService;
use H5PEditorEndpoints;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * H5P authoring (editor + AJAX), package upload, playback and result capture.
 *
 * The editor and its AJAX, plus the player and result sink, are authenticated
 * with short-lived launch tokens (cache) rather than the SPA bearer token,
 * because they run inside cross-origin iframes that cannot forward headers.
 */
class H5PController extends Controller
{
    public function __construct(
        private ActivityMaterialService $materialService,
        private ActivityResultService $resultService,
    ) {}

    // ── Instructor: upload pre-built .h5p ───────────────────────────────

    /**
     * POST /api/v1/activities/{id}/h5p-upload
     */
    public function upload(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        if ($activity->type !== 'h5p') {
            return response()->json(['message' => 'Activity is not an H5P type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:524288',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'h5p') {
            return response()->json(['message' => 'File must be a .h5p package.'], 422);
        }

        // Keep the original archive for AI text extraction + re-download.
        $packagePath = $file->store("h5p/{$activity->course_id}", 'public');

        // The H5P validator requires the file path to literally end in ".h5p",
        // but store() saves it as a random *.zip — so install from a .h5p temp copy.
        $h5pTmp = storage_path('app/h5p-upload-' . Str::random(10) . '.h5p');
        copy(storage_path('app/public/' . $packagePath), $h5pTmp);

        $h5p       = new H5PService();
        $contentId = $h5p->installPackage($h5pTmp);
        @unlink($h5pTmp);

        if (! $contentId) {
            Storage::disk('public')->delete($packagePath);
            $errors = $h5p->framework->getMessages('error') ?? [];
            $msg    = ! empty($errors) ? ($errors[0]->message ?? 'Invalid H5P package.') : 'Invalid H5P package.';
            return response()->json(['message' => $msg], 422);
        }

        $settings = $activity->settings ?? [];
        $settings['h5pContentId'] = $contentId;
        $settings['packagePath']  = $packagePath;
        $settings['fileName']     = $file->getClientOriginalName();
        $settings['mimeType']     = 'application/zip';
        $settings['fileSize']     = $file->getSize();
        $activity->settings       = $settings;
        $activity->save();

        // Interactive H5P packages are self-contained — they are intentionally NOT
        // run through the course-material text-extraction/adaptive pipeline (its
        // content.json is structural JSON, not meaningful prose for personalization).

        return response()->json([
            'message' => 'H5P package uploaded successfully.',
            'data'    => ['h5p_content_id' => $contentId],
        ]);
    }

    // ── Instructor: save authored content ───────────────────────────────

    /**
     * POST /api/v1/activities/{id}/h5p/content
     * Body: { library: "H5P.X 1.2", params: "<json string>" }
     */
    public function saveContent(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        if ($activity->type !== 'h5p') {
            return response()->json(['message' => 'Activity is not an H5P type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'library' => 'required|string',
            'params'  => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings  = $activity->settings ?? [];
        $existingId = isset($settings['h5pContentId']) ? (int) $settings['h5pContentId'] : null;

        $h5p       = new H5PService();
        $contentId = $h5p->saveEditorContent($request->input('library'), $request->input('params'), $existingId);

        if (! $contentId) {
            return response()->json(['message' => 'Could not save H5P content. The selected content type may not be installed.'], 422);
        }

        $settings['h5pContentId'] = $contentId;
        $activity->settings       = $settings;
        $activity->save();

        return response()->json([
            'message' => 'H5P content saved.',
            'data'    => ['h5p_content_id' => $contentId],
        ]);
    }

    /**
     * DELETE /api/v1/activities/{id}/h5p (instructor) — remove content + archive.
     */
    public function destroy(string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $settings = $activity->settings ?? [];

        if (! empty($settings['h5pContentId'])) {
            (new H5PService())->deleteContent((int) $settings['h5pContentId']);
        }
        if (! empty($settings['packagePath'])) {
            Storage::disk('public')->delete($settings['packagePath']);
        }

        $materialIds = CourseMaterial::where('activity_id', $activity->id)->pluck('id');
        ContentChunk::where('content_source', 'course_material')->whereIn('content_id', $materialIds)->delete();
        CourseMaterial::where('activity_id', $activity->id)->delete();

        unset($settings['h5pContentId'], $settings['packagePath'], $settings['fileName'], $settings['mimeType'], $settings['fileSize']);
        $activity->settings = $settings;
        $activity->save();

        return response()->json(['message' => 'H5P content removed.']);
    }

    // ── Instructor: editor session ──────────────────────────────────────

    /**
     * POST /api/v1/h5p/editor-session/{activityId?}
     * Issue a token and return the editor iframe URL.
     */
    public function editorSession(Request $request, ?string $activityId = null): JsonResponse
    {
        $contentId = null;
        if ($activityId) {
            $activity  = Activity::findOrFail($activityId);
            $contentId = $activity->settings['h5pContentId'] ?? null;
        }

        $token = Str::random(48);
        Cache::put("h5p_editor:{$token}", [
            'user_id'    => $request->user()->id,
            'content_id' => $contentId ? (int) $contentId : null,
        ], now()->addHours(4));

        return response()->json([
            'editor_url' => url("/api/v1/h5p/editor/{$token}"),
        ]);
    }

    /**
     * GET /api/v1/h5p/editor/{token} — token-authed editor iframe page.
     */
    public function editor(string $token): View|JsonResponse
    {
        $session = Cache::get("h5p_editor:{$token}");
        if (! $session) {
            return response()->json(['message' => 'Editor session expired.'], 410);
        }
        $this->actAs($session['user_id']);

        $h5p      = new H5PService();
        $ajaxPath = url("/api/v1/h5p/ajax") . "?token={$token}&action=";
        $data     = $h5p->editorData($session['content_id'] ?? null, $ajaxPath);

        return view('h5p.editor', $data);
    }

    /**
     * GET|POST /api/v1/h5p/ajax?token=..&action=.. — token-authed editor AJAX.
     */
    public function ajax(Request $request): Response
    {
        $session = Cache::get('h5p_editor:' . $request->query('token'));
        if (! $session) {
            return response('{"success":false,"message":"Session expired"}', 410)
                ->header('Content-Type', 'application/json');
        }
        $this->actAs($session['user_id']);

        $h5p    = new H5PService();
        $ajax   = $h5p->ajaxHandler;
        $action = (string) $request->query('action');
        $token  = (string) $request->query('token');

        ob_start();
        switch ($action) {
            case 'libraries':
                // Single library details vs full list.
                if ($request->filled('machineName')) {
                    $ajax->action(
                        H5PEditorEndpoints::SINGLE_LIBRARY,
                        $request->input('machineName'),
                        $request->input('majorVersion'),
                        $request->input('minorVersion'),
                        $request->input('languageCode', 'en'),
                        '',
                        $h5p->filesUrl,
                        $request->input('default-language', '')
                    );
                } else {
                    $ajax->action(H5PEditorEndpoints::LIBRARIES);
                }
                break;
            case 'single-library':
                $ajax->action(
                    H5PEditorEndpoints::SINGLE_LIBRARY,
                    $request->input('machineName'),
                    $request->input('majorVersion'),
                    $request->input('minorVersion'),
                    $request->input('languageCode', 'en'),
                    '',
                    $h5p->filesUrl,
                    $request->input('default-language', '')
                );
                break;
            case 'content-type-cache':
                $ajax->action(H5PEditorEndpoints::CONTENT_TYPE_CACHE);
                break;
            case 'library-install':
                $ajax->action(H5PEditorEndpoints::LIBRARY_INSTALL, $token, $request->input('id'));
                break;
            case 'library-upload':
                $upload = $request->file('h5p');
                $ajax->action(H5PEditorEndpoints::LIBRARY_UPLOAD, $token, $upload ? $upload->getRealPath() : '', $request->input('contentId', 0));
                break;
            case 'files':
                $ajax->action(H5PEditorEndpoints::FILES, $token, 0);
                break;
            case 'translations':
                $ajax->action(H5PEditorEndpoints::TRANSLATIONS, $request->input('language', 'en'));
                break;
            case 'filter':
                $ajax->action(H5PEditorEndpoints::FILTER, $token, $request->input('libraryParameters'));
                break;
            default:
                echo '{"success":false,"message":"Unknown action"}';
        }
        $output = ob_get_clean();

        return response($output ?: '{"success":false}')->header('Content-Type', 'application/json');
    }

    // ── Student: launch + play + results ────────────────────────────────

    /**
     * POST /api/v1/student/activities/{id}/h5p/launch
     */
    public function launch(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $contentId = $activity->settings['h5pContentId'] ?? null;
        if (! $contentId) {
            return response()->json(['message' => 'This H5P activity has no content yet.'], 404);
        }

        $token = Str::random(48);
        Cache::put("h5p_play:{$token}", [
            'activity_id' => $id,
            'content_id'  => (int) $contentId,
            'user_id'     => $request->user()->id,
            'opened'      => time(),
        ], now()->addHours(4));

        return response()->json([
            'launch_url' => url("/api/v1/h5p/play/{$token}"),
        ]);
    }

    /**
     * GET /api/v1/h5p/play/{token} — token-authed player iframe page.
     */
    public function play(string $token): View|JsonResponse
    {
        $session = Cache::get("h5p_play:{$token}");
        if (! $session) {
            return response()->json(['message' => 'Launch link expired. Please reopen the activity.'], 410);
        }

        $h5p  = new H5PService();
        $data = $h5p->playerData((int) $session['content_id']);
        if (! $data) {
            return response()->json(['message' => 'H5P content could not be loaded.'], 404);
        }

        $data['token']      = $token;
        $data['contentId']  = (int) $session['content_id'];
        $data['resultsUrl'] = url("/api/v1/h5p/results/{$token}");

        return view('h5p.play', $data);
    }

    /**
     * POST /api/v1/h5p/results/{token} — capture an xAPI score and grade.
     */
    public function results(Request $request, string $token): JsonResponse
    {
        $session = Cache::get("h5p_play:{$token}");
        if (! $session) {
            return response()->json(['message' => 'Launch link expired.'], 410);
        }

        $score    = (float) $request->input('score', 0);
        $maxScore = (float) $request->input('max_score', 0);
        $finished = (bool) $request->input('finished', false);

        // A scoreless completion ping (reading-only content) must never wipe a
        // quiz score that was already captured for this content/user.
        $existing = DB::table('h5p_results')
            ->where(['content_id' => $session['content_id'], 'user_id' => $session['user_id']])
            ->first();
        if ($maxScore <= 0 && $existing && $existing->max_score > 0) {
            $score    = (float) $existing->score;
            $maxScore = (float) $existing->max_score;
        }

        DB::table('h5p_results')->updateOrInsert(
            ['content_id' => $session['content_id'], 'user_id' => $session['user_id']],
            [
                'score'     => (int) round($score),
                'max_score' => (int) round($maxScore),
                'opened'    => $session['opened'] ?? time(),
                'finished'  => $finished ? time() : 0,
                'time'      => time() - ($session['opened'] ?? time()),
            ]
        );

        $activity = Activity::find($session['activity_id']);
        if ($activity) {
            if ($maxScore > 0) {
                // Graded content (questions/quiz) → record the real score.
                $this->resultService->recordScore($activity, $session['user_id'], $score, $maxScore);
            } elseif ($finished) {
                // Reading-only content (no scorable questions) → completion credit = full marks.
                $gradeMax = (float) ($activity->grade_max ?? 0) ?: 100;
                $this->resultService->recordScore($activity, $session['user_id'], $gradeMax, $gradeMax);
            }
            $this->resultService->recordCompletion($activity, $session['user_id'], $finished ? 'completed' : 'attempted');
        }

        return response()->json(['message' => 'Result recorded.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Authenticate the request as the token's owner so Auth::id() resolves. */
    private function actAs(string $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            Auth::setUser($user);
        }
    }
}
