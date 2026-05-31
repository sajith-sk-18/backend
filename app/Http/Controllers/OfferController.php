<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    /**
     * GET /api/products/{id}/offers   (public — only live offers)
     */
    public function indexForProduct(int $id): JsonResponse
    {
        $offers = Offer::with(['product:id,name,price', 'bundleProduct.images', 'bundleProduct.category'])
            ->where('product_id', $id)
            ->live()
            ->latest()
            ->get();

        return response()->json($offers);
    }

    /**
     * GET /api/admin/offers
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $q = Offer::with(['product:id,name,brand,price', 'bundleProduct:id,name,brand,price']);

        if ($search = $request->input('q')) {
            $q->where('title', 'like', "%{$search}%");
        }
        if ($request->filled('product_id')) {
            $q->where('product_id', $request->input('product_id'));
        }
        if ($request->boolean('only_live')) {
            $q->live();
        }

        return response()->json($q->latest()->paginate(10));
    }

    public function adminShow(int $id): JsonResponse
    {
        $offer = Offer::with(['product', 'bundleProduct'])->findOrFail($id);
        return response()->json($offer);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $offer = Offer::create($data);
        return response()->json(
            $offer->load(['product:id,name,brand,price', 'bundleProduct:id,name,brand,price']),
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $offer = Offer::findOrFail($id);
        $data  = $this->validatePayload($request, $id);
        $offer->update($data);
        return response()->json(
            $offer->fresh()->load(['product:id,name,brand,price', 'bundleProduct:id,name,brand,price'])
        );
    }

    public function destroy(int $id): JsonResponse
    {
        Offer::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    private function validatePayload(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:1000',
            'product_id'        => 'required|exists:products,id',
            'type'              => ['required', Rule::in(['percent', 'flat', 'bundle'])],

            'discount_value'    => 'nullable|numeric|min:0|required_if:type,percent|required_if:type,flat',

            'bundle_product_id' => 'nullable|exists:products,id|required_if:type,bundle|different:product_id',
            'bundle_discount'   => 'nullable|numeric|min:0|max:100|required_if:type,bundle',

            'starts_at'         => 'nullable|date',
            'ends_at'           => 'nullable|date|after_or_equal:starts_at',
            'is_active'         => 'nullable|boolean',
        ]);
    }
}
