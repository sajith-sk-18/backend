<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * Filters: q (search), category_id, brand, min_price, max_price
     * Pagination: page, per_page (default 12)
     */
    public function index(Request $request): JsonResponse
    {
        $q = Product::query()
            ->with(['images', 'category', 'liveOffers'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('is_active', true);

        if ($search = $request->input('q')) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $q->where('category_id', $categoryId);
        }

        if ($brand = $request->input('brand')) {
            $q->where('brand', $brand);
        }

        if ($min = $request->input('min_price')) {
            $q->where('price', '>=', (float) $min);
        }

        if ($max = $request->input('max_price')) {
            $q->where('price', '<=', (float) $max);
        }

        if ($request->boolean('featured')) {
            $q->where('is_featured', true);
        }

        $perPage = min((int) $request->input('per_page', 12), 50);
        return response()->json($q->latest()->paginate($perPage));
    }

    /**
     * GET /api/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with([
                'images',
                'category',
                'approvedReviews',
                'liveOffers.bundleProduct.images',
                'liveOffers.bundleProduct.category',
            ])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('is_active', true)
            ->findOrFail($id);

        // ensure discounted_price computes correctly per offer
        $product->liveOffers->each(fn ($o) => $o->setRelation('product', $product));

        return response()->json($product);
    }

    /**
     * GET /api/products/{id}/related (public)
     * Same category, excluding self, ordered by featured DESC then rating DESC.
     */
    public function related(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        if (!$product->is_active) {
            return response()->json([]);
        }

        $related = Product::with(['images', 'category'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->orderByDesc('is_featured')
            ->orderByDesc('approved_reviews_avg_rating')
            ->limit(6)
            ->get();

        return response()->json($related);
    }

    /**
     * GET /api/admin/products/{id} (admin sees inactive too)
     */
    public function adminShow(int $id): JsonResponse
    {
        $product = Product::with(['images', 'category', 'reviews'])->findOrFail($id);
        return response()->json($product);
    }

    /**
     * GET /api/admin/products
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $q = Product::with(['images', 'category'])
            ->withCount('reviews');

        if ($search = $request->input('q')) {
            $q->where('name', 'like', "%{$search}%");
        }

        return response()->json($q->latest()->paginate(10));
    }

    /**
     * POST /api/admin/products
     * Multipart with images[].
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'brand'       => 'required|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'specs'       => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::create([
                'name'        => $data['name'],
                'brand'       => $data['brand'],
                'category_id' => $data['category_id'],
                'price'       => $data['price'],
                'stock'       => $data['stock'] ?? 0,
                'description' => $data['description'] ?? null,
                'specs'       => $data['specs'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'is_active'   => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);

            $this->saveImages($request, $product);

            return $product;
        });

        // Initial stock might already be low — surface that immediately.
        Notification::lowStockFor($product);

        return response()->json($product->load('images', 'category'), 201);
    }

    /**
     * PUT/POST /api/admin/products/{id}
     * Use POST with _method=PUT if sending multipart with new images.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'brand'       => 'sometimes|required|string|max:100',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price'       => 'sometimes|required|numeric|min:0',
            'stock'       => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'specs'       => 'nullable|array',
            'is_featured' => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_image_ids'   => 'nullable|array',
            'remove_image_ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($request, $data, $product) {
            $product->update(array_filter($data, fn ($k) =>
                !in_array($k, ['images', 'remove_image_ids']), ARRAY_FILTER_USE_KEY));

            // Remove explicitly-marked images
            if (!empty($data['remove_image_ids'])) {
                $toDelete = ProductImage::where('product_id', $product->id)
                    ->whereIn('id', $data['remove_image_ids'])->get();
                foreach ($toDelete as $img) {
                    Storage::disk('public')->delete($img->path);
                    $img->delete();
                }
            }

            $this->saveImages($request, $product);
        });

        $fresh = $product->fresh();
        if ((int) $fresh->stock > Notification::LOW_STOCK_THRESHOLD) {
            Notification::markEntityRead(Product::class, $fresh->id, 'low_stock');
        } else {
            Notification::lowStockFor($fresh);
        }

        return response()->json($fresh->load('images', 'category'));
    }

    /**
     * PATCH /api/admin/products/{id}/stock
     * Lightweight stock-only update.
     * Accepts:
     *   { "stock": 42 }                 → set absolute
     *   { "stock_delta": +5 / -2 }      → relative adjustment
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'stock'       => 'sometimes|integer|min:0',
            'stock_delta' => 'sometimes|integer',
        ]);

        $product = Product::findOrFail($id);

        if (array_key_exists('stock', $data)) {
            $product->stock = (int) $data['stock'];
        } elseif (array_key_exists('stock_delta', $data)) {
            $product->stock = max(0, (int) $product->stock + (int) $data['stock_delta']);
        } else {
            return response()->json(['message' => 'Provide stock or stock_delta.'], 422);
        }

        $product->save();

        if ((int) $product->stock > Notification::LOW_STOCK_THRESHOLD) {
            // Restocked above threshold — any open low-stock alert is now stale.
            Notification::markEntityRead(Product::class, $product->id, 'low_stock');
        } else {
            // Emit (or skip via dedup) a low-stock notification.
            Notification::lowStockFor($product);
        }

        return response()->json(['id' => $product->id, 'stock' => $product->stock]);
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::with('images')->findOrFail($id);
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->path);
        }
        // Product is gone; any open notifications about it are now stale.
        Notification::markEntityRead(Product::class, $product->id);
        $product->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    private function saveImages(Request $request, Product $product): void
    {
        if (!$request->hasFile('images')) {
            return;
        }
        $existingCount = $product->images()->count();
        foreach ($request->file('images') as $i => $file) {
            $path = $file->store("products/{$product->id}", 'public');
            ProductImage::create([
                'product_id' => $product->id,
                'path'       => $path,
                'is_primary' => $existingCount === 0 && $i === 0,
                'sort_order' => $existingCount + $i,
            ]);
        }
    }
}
