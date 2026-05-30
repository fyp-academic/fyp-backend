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

class FileController extends Controller
{
    public function __construct(
        private ActivityMaterialService $materialService,
    ) {}

    /**
     * POST /api/v1/activities/{id}/file-upload
     * Upload a file for a file activity.
     * Stores in storage/app/public/files/{course_id}/
     */
    public function upload(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        if ($activity->type !== 'file') {
            return response()->json(['message' => 'Activity is not a file type.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $courseId = $activity->course_id;
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        // Store in dedicated files folder per course
        $path = $file->store("files/{$courseId}", 'public');

        // Update activity settings with file metadata
        $settings = $activity->settings ?? [];
        $settings['filePath'] = $path;
        $settings['fileUrl'] = asset('storage/' . $path);
        $settings['fileName'] = $fileName;
        $settings['mimeType'] = $file->getMimeType();
        $settings['fileSize'] = $file->getSize();
        $activity->settings = $settings;
        $activity->save();

        $materialType = $this->materialService->detectMaterialType($file->getMimeType(), $fileName);
        $this->materialService->syncFromUpload(
            $activity,
            $path,
            $fileName,
            $file->getMimeType(),
            (int) $file->getSize(),
            $materialType,
            Auth::id(),
        );

        return response()->json([
            'message' => 'File uploaded successfully.',
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
     * DELETE /api/v1/activities/{id}/file
     * Remove the uploaded file for an activity.
     */
    public function destroy(string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        if ($activity->type !== 'file') {
            return response()->json(['message' => 'Activity is not a file type.'], 422);
        }

        $settings = $activity->settings ?? [];
        $filePath = $settings['filePath'] ?? null;

        if ($filePath) {
            Storage::disk('public')->delete($filePath);
        }

        $materialIds = CourseMaterial::where('activity_id', $activity->id)->pluck('id');
        ContentChunk::where('content_source', 'course_material')
            ->whereIn('content_id', $materialIds)
            ->delete();
        CourseMaterial::where('activity_id', $activity->id)->delete();

        unset($settings['filePath'], $settings['fileUrl'], $settings['fileName'], $settings['mimeType'], $settings['fileSize']);
        $activity->settings = $settings;
        $activity->save();

        return response()->json(['message' => 'File removed successfully.']);
    }
}
