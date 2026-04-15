<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories
     * Return all categories including parent/child hierarchy and course counts.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * POST /api/v1/categories
     * Create a new top-level or nested course category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'parent_id'   => 'sometimes|nullable|string|exists:categories,id',
            'id_number'   => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'id'           => Str::uuid()->toString(),
            'name'         => $request->name,
            'description'  => $request->input('description'),
            'parent_id'    => $request->input('parent_id'),
            'id_number'    => $request->input('id_number', ''),
            'course_count' => 0,
            'child_count'  => 0,
        ]);

        if ($category->parent_id) {
            Category::where('id', $category->parent_id)->increment('child_count');
        }

        return response()->json(['message' => 'Category created.', 'data' => $category], 201);
    }

    /**
     * PUT /api/v1/categories/{id}
     * Update a category's name, description, or parent assignment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'parent_id'   => 'sometimes|nullable|string|exists:categories,id',
            'id_number'   => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldParentId = $category->parent_id;
        $category->update($request->only(['name', 'description', 'parent_id', 'id_number']));

        if ($request->has('parent_id') && $oldParentId !== $request->parent_id) {
            if ($oldParentId) {
                Category::where('id', $oldParentId)->decrement('child_count');
            }
            if ($request->parent_id) {
                Category::where('id', $request->parent_id)->increment('child_count');
            }
        }

        return response()->json(['message' => 'Category updated.', 'data' => $category]);
    }

    /**
     * DELETE /api/v1/categories/{id}
     * Remove a category; returns 422 if courses are still assigned to it.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->course_count > 0 || $category->courses()->exists()) {
            return response()->json(['message' => 'Cannot delete category with assigned courses.'], 422);
        }

        if ($category->parent_id) {
            Category::where('id', $category->parent_id)->decrement('child_count');
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
