<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReviewController extends Controller
{
    /** GET /api/products/{id}/reviews (public, approved only) */
    public function indexForProduct(int $productId): JsonResponse
    {
        Product::findOrFail($productId);
        return response()->json(
            Review::where('product_id', $productId)
                ->where('is_approved', true)
                ->latest()
                ->get()
        );
    }

    /** GET /api/admin/reviews */
    public function adminIndex(Request $request): JsonResponse
    {
        $q = Review::with('product:id,name')->latest();
        if ($request->filled('status')) {
            $q->where('is_approved', $request->status === 'approved');
        }
        return response()->json($q->paginate(10));
    }

    /**
     * POST /api/admin/reviews  (admin)
     *
     * Client 16-Sep: the storefront no longer takes reviews from visitors --
     * the shop writes the two or three it wants shown against each product.
     * Those are authored here and land APPROVED, so they appear immediately
     * without a second moderation step.
     */
    public function adminStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'customer_name'  => 'required|string|max:120',
            'customer_email' => 'nullable|email|max:190',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'required|string|min:5|max:2000',
        ]);

        $review = Review::create([
            'product_id'     => $data['product_id'],
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'rating'         => $data['rating'],
            'comment'        => $data['comment'],
            'is_approved'    => true,
        ]);

        return response()->json($review->load('product:id,name'), 201);
    }

    /**
     * PUT /api/admin/reviews/{id}  (admin)
     *
     * Editing what is shown, without deleting and re-adding it.
     */
    public function adminUpdate(Request $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        $data = $request->validate([
            'customer_name' => 'sometimes|required|string|max:120',
            'rating'        => 'sometimes|required|integer|min:1|max:5',
            'comment'       => 'sometimes|required|string|min:5|max:2000',
        ]);

        $review->update($data);

        return response()->json($review->load('product:id,name'));
    }

    /** POST /api/admin/reviews/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update(['is_approved' => true]);

        // The "new review" notification has been acted on — mark it read.
        Notification::markEntityRead(Review::class, $review->id, 'review');

        return response()->json($review);
    }

    /** DELETE /api/admin/reviews/{id} */
    public function destroy(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        Notification::markEntityRead(Review::class, $review->id);
        $review->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
