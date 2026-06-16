<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ContentChunk;
use App\Models\CourseMaterial;
use App\Models\ScormTrack;
use App\Services\ActivityMaterialService;
use App\Services\ActivityResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * SCORM 1.2 / 2004 package management and runtime.
 *
 * Instructors upload a .zip; it is extracted under storage/app/public/scorm and
 * the imsmanifest.xml is parsed for the SCORM version and launch SCO. Students
 * receive a short-lived launch token; the player wrapper (served same-origin as
 * the SCO) exposes the SCORM API and persists CMI data via the token.
 */
class ScormPackageController extends Controller
{
    /** Status CMI elements across SCORM 1.2 and 2004. */
    private const STATUS_ELEMENTS = [
        'cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status',
    ];

    public function __construct(
        private ActivityMaterialService $materialService,
        private ActivityResultService $resultService,
    ) {}

    // ── Instructor: upload ──────────────────────────────────────────────

    /**
     * POST /api/v1/activities/{id}/scorm-upload
     * Accept a SCORM .zip, extract it, parse the manifest and store metadata.
     */
    public function upload(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        if ($activity->type !== 'scorm') {
            return response()->json(['message' => 'Activity is not a SCORM type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:524288', // 512 MB
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file     = $request->file('file');
        $ext      = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['zip'], true)) {
            return response()->json(['message' => 'SCORM package must be a .zip file.'], 422);
        }

        $courseId = $activity->course_id;
        $fileName = $file->getClientOriginalName();

        // Remove any previous extraction for this activity.
        $relDir = "scorm/{$courseId}/{$activity->id}";
        $absDir = storage_path("app/public/{$relDir}");
        if (is_dir($absDir)) {
            File::deleteDirectory($absDir);
        }
        File::makeDirectory($absDir, 0755, true, true);

        // Keep the original archive (used for AI text extraction + re-download).
        $packagePath = $file->store("scorm/{$courseId}", 'public');

        $zip = new \ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            return response()->json(['message' => 'Could not open the SCORM archive.'], 422);
        }
        $zip->extractTo($absDir);
        $zip->close();

        // Locate the manifest (may sit inside a wrapper folder).
        $manifest = $this->findManifest($absDir);
        if (! $manifest) {
            File::deleteDirectory($absDir);
            Storage::disk('public')->delete($packagePath);
            return response()->json(['message' => 'No imsmanifest.xml found — not a valid SCORM package.'], 422);
        }

        $packageRoot = dirname($manifest);
        $subdir      = trim(str_replace($absDir, '', $packageRoot), '/');
        $scormPath   = $subdir === '' ? $relDir : "{$relDir}/{$subdir}";

        [$version, $launchHref] = $this->parseManifest($manifest);
        if (! $launchHref) {
            File::deleteDirectory($absDir);
            Storage::disk('public')->delete($packagePath);
            return response()->json(['message' => 'No launchable resource found in the SCORM manifest.'], 422);
        }

        $settings = $activity->settings ?? [];
        $settings['scormPath']    = $scormPath;
        $settings['launchHref']   = $launchHref;
        $settings['scormVersion'] = $version;
        $settings['packagePath']  = $packagePath;
        $settings['fileName']     = $fileName;
        $settings['mimeType']     = 'application/zip';
        $settings['fileSize']     = $file->getSize();
        $activity->settings       = $settings;
        $activity->save();

        // SCORM packages are self-contained interactive content — intentionally NOT
        // run through the course-material text-extraction/adaptive pipeline.

        return response()->json([
            'message' => 'SCORM package uploaded successfully.',
            'data'    => [
                'scorm_path'    => $scormPath,
                'launch_href'   => $launchHref,
                'scorm_version' => $version,
                'file_name'     => $fileName,
                'size'          => $file->getSize(),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/activities/{id}/scorm
     * Remove the extracted package, archive and derived materials.
     */
    public function destroy(string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $settings = $activity->settings ?? [];

        $relDir = "scorm/{$activity->course_id}/{$activity->id}";
        $absDir = storage_path("app/public/{$relDir}");
        if (is_dir($absDir)) {
            File::deleteDirectory($absDir);
        }
        if (! empty($settings['packagePath'])) {
            Storage::disk('public')->delete($settings['packagePath']);
        }

        $materialIds = CourseMaterial::where('activity_id', $activity->id)->pluck('id');
        ContentChunk::where('content_source', 'course_material')
            ->whereIn('content_id', $materialIds)
            ->delete();
        CourseMaterial::where('activity_id', $activity->id)->delete();

        unset(
            $settings['scormPath'], $settings['launchHref'], $settings['scormVersion'],
            $settings['packagePath'], $settings['fileName'], $settings['mimeType'], $settings['fileSize']
        );
        $activity->settings = $settings;
        $activity->save();

        return response()->json(['message' => 'SCORM package removed.']);
    }

    // ── Student: launch + runtime ───────────────────────────────────────

    /**
     * POST /api/v1/student/activities/{id}/scorm/launch
     * Issue a short-lived launch token and return the player URL.
     */
    public function launch(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $settings = $activity->settings ?? [];

        if (empty($settings['scormPath']) || empty($settings['launchHref'])) {
            return response()->json(['message' => 'This SCORM activity has no package uploaded yet.'], 404);
        }

        $user    = $request->user();
        $attempt = (int) (ScormTrack::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->max('attempt') ?? 1);
        $attempt = max(1, $attempt);

        $token = Str::random(48);
        Cache::put("scorm_launch:{$token}", [
            'activity_id' => $id,
            'user_id'     => $user->id,
            'attempt'     => $attempt,
        ], now()->addHours(4));

        return response()->json([
            'launch_url' => url("/api/v1/scorm/play/{$token}"),
        ]);
    }

    /**
     * GET /api/v1/scorm/play/{token}
     * Render the SCORM player wrapper (token-authenticated).
     */
    public function play(string $token): View|JsonResponse
    {
        $session = Cache::get("scorm_launch:{$token}");
        if (! $session) {
            return response()->json(['message' => 'Launch link expired. Please reopen the activity.'], 410);
        }

        $activity = Activity::findOrFail($session['activity_id']);
        $settings = $activity->settings ?? [];

        $scoUrl = asset('storage/' . trim($settings['scormPath'], '/') . '/' . ltrim($settings['launchHref'], '/'));

        return view('scorm.play', [
            'token'     => $token,
            'scoUrl'    => $scoUrl,
            'version'   => $settings['scormVersion'] ?? '1.2',
            'title'     => $activity->name,
            'trackUrl'  => url("/api/v1/scorm/track/{$token}"),
        ]);
    }

    /**
     * POST /api/v1/scorm/track/{token}
     * Persist a CMI element (token-authenticated) and grade on completion.
     */
    public function track(Request $request, string $token): JsonResponse
    {
        $session = Cache::get("scorm_launch:{$token}");
        if (! $session) {
            return response()->json(['message' => 'Launch link expired.'], 410);
        }

        $validator = Validator::make($request->all(), [
            'element' => 'required|string|max:255',
            'value'   => 'present|string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($session['activity_id']);
        $userId   = $session['user_id'];
        $attempt  = (int) ($session['attempt'] ?? 1);
        $element  = $request->element;
        $value    = (string) $request->input('value', '');

        $status = $this->statusFromElement($element, $value);

        ScormTrack::updateOrCreate(
            [
                'activity_id' => $activity->id,
                'student_id'  => $userId,
                'attempt'     => $attempt,
                'element'     => $element,
            ],
            [
                'id'        => (string) Str::uuid(),
                'value'     => $value,
                'status'    => $status ?? 'not_attempted',
                'score_raw' => $this->isScoreRaw($element) ? (float) $value : null,
                'score_max' => $this->isScoreMax($element) ? (float) $value : null,
            ]
        );

        // A terminal status means the SCO finished — grade + complete.
        if ($status && in_array($status, ['completed', 'passed', 'failed'], true)) {
            $this->finalize($activity, $userId, $attempt, $status);
        }

        return response()->json(['message' => 'Track recorded.']);
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function finalize(Activity $activity, string $userId, int $attempt, string $status): void
    {
        $scoreRaw = $this->latestScore($activity->id, $userId, $attempt, [
            'cmi.core.score.raw', 'cmi.score.raw',
        ]);
        $scoreMax = $this->latestScore($activity->id, $userId, $attempt, [
            'cmi.core.score.max', 'cmi.score.max',
        ]);

        if ($scoreRaw !== null) {
            $this->resultService->recordScore($activity, $userId, $scoreRaw, $scoreMax);
        }

        $completionType = in_array($status, ['completed', 'passed'], true) ? 'completed' : 'attempted';
        $this->resultService->recordCompletion($activity, $userId, $completionType);
    }

    private function latestScore(string $activityId, string $userId, int $attempt, array $elements): ?float
    {
        $track = ScormTrack::where('activity_id', $activityId)
            ->where('student_id', $userId)
            ->where('attempt', $attempt)
            ->whereIn('element', $elements)
            ->latest('updated_at')
            ->first();

        return $track && $track->value !== null && is_numeric($track->value) ? (float) $track->value : null;
    }

    private function statusFromElement(string $element, string $value): ?string
    {
        if (! in_array($element, self::STATUS_ELEMENTS, true)) {
            return null;
        }
        $v = strtolower(trim($value));

        return match ($v) {
            'completed', 'passed', 'failed', 'incomplete' => $v,
            'not attempted', 'not_attempted', 'unknown', '' => 'not_attempted',
            'browsed' => 'incomplete',
            default => $v,
        };
    }

    private function isScoreRaw(string $element): bool
    {
        return in_array($element, ['cmi.core.score.raw', 'cmi.score.raw'], true);
    }

    private function isScoreMax(string $element): bool
    {
        return in_array($element, ['cmi.core.score.max', 'cmi.score.max'], true);
    }

    /** Recursively locate the imsmanifest.xml inside the extracted package. */
    private function findManifest(string $dir): ?string
    {
        $direct = $dir . '/imsmanifest.xml';
        if (file_exists($direct)) {
            return $direct;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() === 'imsmanifest.xml') {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Parse the manifest for [version, launchHref].
     *
     * @return array{0:string,1:?string}
     */
    private function parseManifest(string $manifestPath): array
    {
        $version    = '1.2';
        $launchHref = null;

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return [$version, null];
        }

        // Version: schemaversion text, then fall back to the adlcp namespace.
        if (preg_match('/<schemaversion[^>]*>\s*([^<]+?)\s*<\/schemaversion>/i', $raw, $m)) {
            $sv = strtolower($m[1]);
            if (str_contains($sv, '2004') || str_contains($sv, 'cam 1.3') || str_contains($sv, '1.3')) {
                $version = '2004';
            }
        }
        if (str_contains($raw, 'adlcp_v1p3') || str_contains($raw, 'adlcp_rootv1p3')) {
            $version = '2004';
        }

        $xml = @simplexml_load_string($raw);
        if ($xml === false) {
            return [$version, null];
        }

        // Map resource identifier -> href.
        $resources = [];
        foreach ($xml->resources->resource ?? [] as $resource) {
            $attrs = $resource->attributes();
            $rid   = (string) ($attrs['identifier'] ?? '');
            $href  = (string) ($attrs['href'] ?? '');
            if ($rid !== '' && $href !== '') {
                $resources[$rid] = $href;
            }
        }

        // Default organization -> first item with an identifierref -> resource href.
        $orgs        = $xml->organizations ?? null;
        $defaultOrg  = $orgs ? (string) ($orgs->attributes()['default'] ?? '') : '';
        $chosenOrg   = null;
        foreach ($orgs->organization ?? [] as $org) {
            $oid = (string) ($org->attributes()['identifier'] ?? '');
            if ($chosenOrg === null) {
                $chosenOrg = $org;
            }
            if ($oid !== '' && $oid === $defaultOrg) {
                $chosenOrg = $org;
                break;
            }
        }

        if ($chosenOrg) {
            $ref = $this->firstItemRef($chosenOrg);
            if ($ref !== null && isset($resources[$ref])) {
                $launchHref = $resources[$ref];
            }
        }

        // Fall back to the first resource that has an href.
        if (! $launchHref && ! empty($resources)) {
            $launchHref = reset($resources);
        }

        // Strip any query string / anchor from the launch href.
        if ($launchHref) {
            $launchHref = preg_replace('/[?#].*$/', '', $launchHref);
        }

        return [$version, $launchHref ?: null];
    }

    /** Depth-first search for the first item carrying an identifierref. */
    private function firstItemRef(\SimpleXMLElement $node): ?string
    {
        foreach ($node->item ?? [] as $item) {
            $ref = (string) ($item->attributes()['identifierref'] ?? '');
            if ($ref !== '') {
                return $ref;
            }
            $nested = $this->firstItemRef($item);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }
}
