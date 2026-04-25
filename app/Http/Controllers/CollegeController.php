<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\College;

class CollegeController extends Controller
{
    public function index(): JsonResponse
    {
        $colleges = College::with('degreeProgrammes')->get();
        return response()->json(['data' => $colleges]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:colleges',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $college = College::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->input('description'),
        ]);

        return response()->json(['message' => 'College created.', 'data' => $college], 201);
    }

    public function show(string $id): JsonResponse
    {
        $college = College::with('degreeProgrammes')->findOrFail($id);
        return response()->json(['data' => $college]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $college = College::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20|unique:colleges,code,' . $id . ',id',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $college->update($request->only(['name', 'code', 'description']));

        return response()->json(['message' => 'College updated.', 'data' => $college]);
    }

    public function destroy(string $id): JsonResponse
    {
        $college = College::findOrFail($id);
        $college->delete();

        return response()->json(['message' => 'College deleted.']);
    }
}
