<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\FolderFile;

class FolderController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/folder-files
     * List all files in a folder activity.
     */
    public function index(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $files = FolderFile::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $files, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/folder-files
     * Add a file reference to a folder.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_name'  => 'required|string|max:255',
            'file_path'  => 'required|string',
            'file_size'  => 'sometimes|nullable|integer|min:0',
            'mime_type'  => 'sometimes|nullable|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = FolderFile::where('activity_id', $id)->max('sort_order') ?? -1;

        $file = FolderFile::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'file_name'   => $request->file_name,
            'file_path'   => $request->file_path,
            'file_size'   => $request->input('file_size'),
            'mime_type'   => $request->input('mime_type'),
            'sort_order'  => $request->input('sort_order', $maxOrder + 1),
        ]);

        return response()->json(['message' => 'File added to folder.', 'data' => $file], 201);
    }

    /**
     * DELETE /api/v1/folder-files/{id}
     * Remove a file from a folder.
     */
    public function destroy(string $id): JsonResponse
    {
        $file = FolderFile::findOrFail($id);
        $file->delete();

        return response()->json(['message' => 'File removed from folder.']);
    }
}
