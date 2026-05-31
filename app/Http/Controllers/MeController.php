<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    /**
     * GET /api/me/enquiries
     * Paginated list of the authed customer's own enquiries.
     */
    public function enquiries(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Enquiry::with('product:id,name,brand,price')
            ->where(function ($w) use ($user) {
                $w->where('customer_id', $user->id)
                  ->orWhere(function ($w2) use ($user) {
                      // Legacy rows: match by email when customer_id is null.
                      $w2->whereNull('customer_id')->where('email', $user->email);
                  });
            })
            ->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        return response()->json($q->paginate(10));
    }

    /** GET /api/me/enquiries/{id}  (so /dashboard/enquiries/{id} thread view works) */
    public function showEnquiry(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $enq = Enquiry::with('product:id,name,brand,price')
            ->where(function ($w) use ($user) {
                $w->where('customer_id', $user->id)
                  ->orWhere(function ($w2) use ($user) {
                      $w2->whereNull('customer_id')->where('email', $user->email);
                  });
            })
            ->findOrFail($id);
        return response()->json($enq);
    }

    /**
     * GET /api/me/reviews
     */
    public function reviews(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Review::with('product:id,name,brand')
            ->where('customer_email', $user->email)
            ->latest();
        return response()->json($q->paginate(10));
    }

    /**
     * GET /api/me/notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Notification::where('user_id', $user->id)->latest();
        if ($request->boolean('unread_only')) {
            $q->whereNull('read_at');
        }
        return response()->json($q->paginate(15));
    }

    /** GET /api/me/notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => Notification::where('user_id', $request->user()->id)
                ->whereNull('read_at')->count(),
        ]);
    }

    /** POST /api/me/notifications/{id}/read */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $n = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $n->markRead();
        return response()->json($n);
    }

    /** POST /api/me/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $marked = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['marked' => $marked]);
    }

    /**
     * PUT /api/me
     * Customer self-profile update: name, phone, optional password change.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name'                  => 'sometimes|required|string|max:255',
            'phone'                 => 'nullable|string|max:30',
            'current_password'      => 'required_with:password|current_password:sanctum',
            'password'              => 'sometimes|string|min:6|confirmed',
        ]);

        // Keep current_password out of the persisted payload
        unset($data['current_password']);
        $user->update($data);

        return response()->json([
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'phone'     => $user->phone,
            'is_active' => (bool) $user->is_active,
            'created_at'=> $user->created_at,
        ]);
    }
}
