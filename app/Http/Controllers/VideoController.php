<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ContentChunk;
use App\Models\CourseMaterial;
use App\Services\ActivityMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    public function __construct(
        private ActivityMaterialService $materialService,
    ) {}

    /**
     * POST /api/v1/activities/{id}/video-upload
     * Upload a video file for a video activity.
     * Stores in storage/app/public/videos/{course_id}/
     */
    public function upload(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        if ($activity->type !== 'video') {
            return response()->json(['message' => 'Activity is not a video type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimes:mp4,webm,ogg,mov,mkv|max:512000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $courseId = $activity->course_id;
        $file = $request->file('video');
        $fileName = $file->getClientOriginalName();

        // Store in dedicated videos folder per course
        $path = $file->store("videos/{$courseId}", 'public');

        // Update activity settings with video metadata
        $settings = $activity->settings ?? [];
        $settings['videoPath'] = $path;
        $settings['videoUrl'] = asset('storage/' . $path);
        $settings['fileName'] = $fileName;
        $settings['mimeType'] = $file->getMimeType();
        $settings['fileSize'] = $file->getSize();
        $activity->settings = $settings;
        $activity->save();

        $this->materialService->syncFromUpload(
            $activity,
            $path,
            $fileName,
            $file->getMimeType(),
            (int) $file->getSize(),
            'video',
            Auth::id(),
        );

        return response()->json([
            'message' => 'Video uploaded successfully.',
            'data' => [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'file_name' => $fileName,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * DELETE /api/v1/activities/{id}/video
     * Remove the uploaded video for an activity.
     */
    public function destroy(string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        if ($activity->type !== 'video') {
            return response()->json(['message' => 'Activity is not a video type.'], 422);
        }

        $settings = $activity->settings ?? [];
        $videoPath = $settings['videoPath'] ?? null;

        if ($videoPath) {
            Storage::disk('public')->delete($videoPath);
        }

        $materialIds = CourseMaterial::where('activity_id', $activity->id)->pluck('id');
        ContentChunk::where('content_source', 'course_material')
            ->whereIn('content_id', $materialIds)
            ->delete();
        CourseMaterial::where('activity_id', $activity->id)->delete();

        unset($settings['videoPath'], $settings['videoUrl'], $settings['fileName'], $settings['mimeType'], $settings['fileSize']);
        $activity->settings = $settings;
        $activity->save();

        return response()->json(['message' => 'Video removed successfully.']);
    }
}
