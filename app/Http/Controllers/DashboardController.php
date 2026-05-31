<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /** GET /api/dashboard (admin) */
    public function index(): JsonResponse
    {
        return response()->json([
            'totals' => [
                'products'          => Product::count(),
                'enquiries_pending' => Enquiry::where('status', 'pending')->count(),
                'enquiries_total'   => Enquiry::count(),
                'reviews_pending'   => Review::where('is_approved', false)->count(),
                'reviews_total'     => Review::count(),
            ],
            'recent_enquiries' => Enquiry::latest()->limit(5)->get(),
            'recent_reviews'   => Review::with('product:id,name')->latest()->limit(5)->get(),
            'recent_products'  => Product::with('category:id,name')->latest()->limit(5)->get(),
        ]);
    }
}
