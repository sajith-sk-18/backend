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
     * POST /api/reviews  (public, lands as is_approved=false)
     * Anyone can post a review. Guests supply their own name/email;
     * logged-in customers have theirs auto-attached. One review per
     * email per product.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|between:1,5',
            'comment'    => 'required|string|min:5|max:1000',
        ];
        // Guests must identify themselves; logged-in users reuse their account.
        if (!$user) {
            $rules['name']  = 'required|string|max:120';
            $rules['email'] = 'required|email|max:190';
        }
        $data = $request->validate($rules);

        $name  = $user?->name  ?? $data['name'];
        $email = $user?->email ?? $data['email'];

        // One review per email per product (the schema dedupes on
        // customer_email; a customer_id FK is a future enhancement).
        $already = Review::where('product_id', $data['product_id'])
            ->where('customer_email', $email)
            ->exists();
        if ($already) {
            return response()->json([
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        $review = Review::create([
            'product_id'     => $data['product_id'],
            'customer_name'  => $name,
            'customer_email' => $email,
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
