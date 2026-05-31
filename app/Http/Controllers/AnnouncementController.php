<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    /** GET /api/announcements  (public — live only) */
    public function index(): JsonResponse
    {
        $list = Announcement::live()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
        return response()->json($list);
    }

    /** GET /api/admin/announcements */
    public function adminIndex(): JsonResponse
    {
        return response()->json(
            Announcement::orderBy('sort_order')->orderByDesc('id')->paginate(10)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $row  = Announcement::create($data);
        return response()->json($row, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row  = Announcement::findOrFail($id);
        $data = $this->validatePayload($request, $id);
        $row->update($data);
        return response()->json($row->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        Announcement::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    private function validatePayload(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'title'       => ($id ? 'sometimes|' : '') . 'required|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'badge_label' => 'nullable|string|max:40',
            'theme'       => ['nullable', Rule::in(['festival','sale','coming','info'])],
            'cta_label'   => 'nullable|string|max:60',
            'cta_url'     => 'nullable|string|max:500',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);
    }
}
