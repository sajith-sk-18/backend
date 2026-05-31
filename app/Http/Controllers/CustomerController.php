<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Notification;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    /**
     * GET /api/admin/customers
     * Filters:
     *   q       — name / email / phone (LIKE)
     *   status  — active | inactive
     *   role    — customer | admin (default: customer)
     */
    public function index(Request $request): JsonResponse
    {
        $q = User::query()
            ->withCount(['enquiries', 'reviews'])
            ->latest();

        $role = $request->input('role', 'customer');
        if (in_array($role, ['customer', 'admin'], true)) {
            $q->where('role', $role);
        }

        if ($search = trim((string) $request->input('q'))) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $q->where('is_active', $request->input('status') === 'active');
        }

        return response()->json($q->paginate(10));
    }

    /**
     * GET /api/admin/customers/{id}
     * Returns the user, their enquiries, and their reviews (with product names).
     */
    public function show(int $id): JsonResponse
    {
        $user = User::withCount(['enquiries', 'reviews'])->findOrFail($id);

        return response()->json([
            'user'       => $user,
            'enquiries'  => Enquiry::where('email', $user->email)->latest()->limit(20)->get(),
            'reviews'    => Review::with('product:id,name,brand')
                                ->where('customer_email', $user->email)
                                ->latest()->limit(20)->get(),
        ]);
    }

    /**
     * PUT /api/admin/customers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'email'     => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'     => 'nullable|string|max:30',
            'role'      => ['sometimes', Rule::in(['customer', 'admin'])],
            'is_active' => 'sometimes|boolean',
        ]);

        // Self-demote / self-deactivate protection
        $me = $request->user();
        if ($me && $me->id === $user->id) {
            if (array_key_exists('role', $data) && $data['role'] !== 'admin') {
                throw ValidationException::withMessages(['role' => ["You can't remove your own admin role."]]);
            }
            if (array_key_exists('is_active', $data) && !$data['is_active']) {
                throw ValidationException::withMessages(['is_active' => ["You can't deactivate your own account."]]);
            }
        }

        $user->update($data);
        return response()->json($user->fresh()->loadCount(['enquiries', 'reviews']));
    }

    /**
     * PATCH /api/admin/customers/{id}/toggle-active
     * Flips is_active. Self-deactivate is blocked.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $me = $request->user();

        if ($me && $me->id === $user->id && $user->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ["You can't deactivate your own account."],
            ]);
        }

        $user->is_active = !$user->is_active;
        $user->save();
        return response()->json($user->fresh()->loadCount(['enquiries', 'reviews']));
    }

    /**
     * DELETE /api/admin/customers/{id}
     * Cascades to delete the user's enquiries + reviews (matched by email),
     * and any low-stock-style notifications about them.
     * Self-delete is blocked.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $me = $request->user();

        if ($me && $me->id === $user->id) {
            throw ValidationException::withMessages([
                'id' => ["You can't delete your own account."],
            ]);
        }

        // Mark any admin notifications about this user's submissions as read,
        // and delete the underlying rows.
        $enqIds  = Enquiry::where('email', $user->email)->pluck('id')->all();
        $revIds  = Review::where('customer_email', $user->email)->pluck('id')->all();
        foreach ($enqIds as $eid) Notification::markEntityRead(Enquiry::class, $eid);
        foreach ($revIds as $rid) Notification::markEntityRead(Review::class,  $rid);
        Enquiry::whereIn('id', $enqIds)->delete();
        Review::whereIn('id', $revIds)->delete();

        // Revoke any active tokens before delete.
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
