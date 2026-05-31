<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WishlistController extends Controller
{
    /** GET /api/me/wishlist — paginated list of wishlist rows + product */
    public function index(Request $request): JsonResponse
    {
        $q = Wishlist::with([
                'product.images',
                'product.category',
                'product.liveOffers',
            ])
            ->where('user_id', $request->user()->id)
            ->latest();
        return response()->json($q->paginate(12));
    }

    /** GET /api/me/wishlist/ids — array of product ids on the wishlist (for batch flagging) */
    public function ids(Request $request): JsonResponse
    {
        return response()->json(
            Wishlist::where('user_id', $request->user()->id)->pluck('product_id')
        );
    }

    /** POST /api/me/wishlist/{productId} — idempotent toggle-on */
    public function store(Request $request, int $productId): JsonResponse
    {
        Wishlist::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $productId,
        ]);
        return response()->json(['wishlisted' => true]);
    }

    /** DELETE /api/me/wishlist/{productId} */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();
        return response()->json(['wishlisted' => false]);
    }
}
