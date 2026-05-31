<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

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

    /**
     * POST /api/reviews  (auth:sanctum, lands as is_approved=false)
     * Logged-in customers post reviews. Name/email come from the user.
     * One review per user per product.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Please sign in to write a review.'], 401);
        }

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|between:1,5',
            'comment'    => 'required|string|min:5|max:1000',
        ]);

        // One review per user per product (matched by email — the existing
        // schema doesn't yet have customer_id; Phase 3 will add the FK).
        $already = Review::where('product_id', $data['product_id'])
            ->where('customer_email', $user->email)
            ->exists();
        if ($already) {
            return response()->json([
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        $review = Review::create([
            'product_id'     => $data['product_id'],
            'customer_name'  => $user->name,
            'customer_email' => $user->email,
            'rating'         => $data['rating'],
            'comment'        => $data['comment'],
            'is_approved'    => false,
        ]);

        // Notify admin
        $product = Product::find($data['product_id']);
        Notification::create([
            'type'        => 'review',
            'title'       => "New {$review->rating}-star review from {$review->customer_name}",
            'body'        => Str::limit(($product?->name ? "on \"{$product->name}\" — " : '') . $review->comment, 160),
            'link'        => '/reviews',
            'entity_type' => Review::class,
            'entity_id'   => $review->id,
        ]);

        return response()->json([
            'message' => 'Thanks! Your review will appear once approved.',
            'review'  => $review,
        ], 201);
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
