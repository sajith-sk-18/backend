<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    /** GET /api/admin/notifications */
    public function index(Request $request): JsonResponse
    {
        // Admin bell shows admin-scoped notifications only (user_id IS NULL).
        $q = Notification::query()->whereNull('user_id')->latest();
        if ($request->boolean('unread_only')) {
            $q->whereNull('read_at');
        }
        return response()->json($q->paginate(15));
    }

    /** GET /api/admin/notifications/unread-count */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread' => Notification::whereNull('user_id')->whereNull('read_at')->count(),
        ]);
    }

    /** POST /api/admin/notifications/{id}/read */
    public function markRead(int $id): JsonResponse
    {
        $n = Notification::findOrFail($id);
        $n->markRead();
        return response()->json($n);
    }

    /** POST /api/admin/notifications/read-all */
    public function markAllRead(): JsonResponse
    {
        $n = Notification::whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['marked' => $n]);
    }

    /** DELETE /api/admin/notifications/{id} */
    public function destroy(int $id): JsonResponse
    {
        Notification::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
