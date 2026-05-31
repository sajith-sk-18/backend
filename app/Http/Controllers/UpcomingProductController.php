<?php

namespace App\Http\Controllers;

use App\Models\UpcomingProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class UpcomingProductController extends Controller
{
    /** GET /api/upcoming  (public) */
    public function index(): JsonResponse
    {
        $list = UpcomingProduct::with('category:id,name,slug')
            ->where('is_active', true)
            ->orderByRaw('expected_at IS NULL, expected_at ASC')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($list);
    }

    /** GET /api/admin/upcoming */
    public function adminIndex(): JsonResponse
    {
        return response()->json(
            UpcomingProduct::with('category:id,name')->latest()->paginate(10)
        );
    }

    /** POST /api/admin/upcoming  (multipart with optional image) */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('upcoming', 'public');
        }
        unset($data['image']);

        $row = UpcomingProduct::create($data);
        return response()->json($row->load('category:id,name'), 201);
    }

    /** PUT/POST /api/admin/upcoming/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $row  = UpcomingProduct::findOrFail($id);
        $data = $this->validatePayload($request, $id);

        if ($request->hasFile('image')) {
            if ($row->image_path) Storage::disk('public')->delete($row->image_path);
            $data['image_path'] = $request->file('image')->store('upcoming', 'public');
        }
        unset($data['image']);

        $row->update($data);
        return response()->json($row->fresh()->load('category:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $row = UpcomingProduct::findOrFail($id);
        if ($row->image_path) Storage::disk('public')->delete($row->image_path);
        $row->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    private function validatePayload(Request $request, ?int $id = null): array
    {
        $rules = [
            'name'           => ($id ? 'sometimes|' : '') . 'required|string|max:255',
            'brand'          => 'nullable|string|max:100',
            'category_id'    => 'nullable|exists:categories,id',
            'expected_at'    => 'nullable|date',
            'teaser'         => 'nullable|string|max:160',
            'description'    => 'nullable|string|max:2000',
            'expected_price' => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ];
        $data = $request->validate($rules);
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        return $data;
    }
}
