<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    /** GET /api/categories (public) */
    public function index(): JsonResponse
    {
        return response()->json(
            Category::withCount('products')->orderBy('name')->get()
        );
    }

    /** POST /api/admin/categories */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:500',
            'image'       => 'nullable|string|max:1000',
        ]);
        $cat = Category::create($data);
        return response()->json($cat, 201);
    }

    /** PUT /api/admin/categories/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'name'        => "sometimes|required|string|max:255|unique:categories,name,{$id}",
            'description' => 'nullable|string|max:500',
            'image'       => 'nullable|string|max:1000',
        ]);
        $cat->update($data);
        return response()->json($cat);
    }

    /** DELETE /api/admin/categories/{id} */
    public function destroy(int $id): JsonResponse
    {
        $cat = Category::withCount('products')->findOrFail($id);
        if ($cat->products_count > 0) {
            return response()->json([
                'message' => "Cannot delete: {$cat->products_count} products still belong to this category.",
            ], 422);
        }
        $cat->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
