<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UpcomingProductController;
use App\Http\Controllers\WishlistController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — customer site
|--------------------------------------------------------------------------
*/

// Anonymous endpoints — tight rate limits to discourage credential stuffing /
// signup spam. Limits are per-IP because there's no authed user yet.
Route::middleware('throttle:5,1')->post('/auth/login',    [AuthController::class, 'login']);
Route::middleware('throttle:3,1')->post('/auth/register', [AuthController::class, 'register']);

// Password reset — both throttled. forgot-password always returns 200 so the
// endpoint can't be used to enumerate registered emails.
Route::middleware('throttle:3,1')->post('/auth/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::middleware('throttle:5,1')->post('/auth/reset-password',  [ForgotPasswordController::class, 'reset']);

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');
Route::get('/products/{id}/reviews', [ReviewController::class, 'indexForProduct'])->whereNumber('id');
Route::get('/products/{id}/offers',  [OfferController::class,  'indexForProduct'])->whereNumber('id');
Route::get('/products/{id}/related', [ProductController::class, 'related'])->whereNumber('id');

Route::get('/upcoming',      [UpcomingProductController::class, 'index']);
Route::get('/announcements', [AnnouncementController::class,    'index']);

/*
|--------------------------------------------------------------------------
| Authenticated (any logged-in user) — customers submit here
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',           [AuthController::class, 'me']);
    Route::put('/me',           [MeController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Customer-submitted writes — throttled per-user to discourage spam.
    Route::middleware('throttle:10,1')->post('/reviews',   [ReviewController::class,  'store']);
    Route::middleware('throttle:10,1')->post('/enquiries', [EnquiryController::class, 'store']);

    // Customer dashboard feeds
    Route::get('/me/enquiries',                 [MeController::class, 'enquiries']);
    Route::get('/me/enquiries/{id}',            [MeController::class, 'showEnquiry'])->whereNumber('id');
    Route::get('/me/reviews',                   [MeController::class, 'reviews']);
    Route::get('/me/notifications',             [MeController::class, 'notifications']);
    Route::get('/me/notifications/unread-count',[MeController::class, 'unreadCount']);
    Route::post('/me/notifications/{id}/read',  [MeController::class, 'markRead'])->whereNumber('id');
    Route::post('/me/notifications/read-all',   [MeController::class, 'markAllRead']);

    // Wishlist
    Route::get('/me/wishlist',                  [WishlistController::class, 'index']);
    Route::get('/me/wishlist/ids',              [WishlistController::class, 'ids']);
    Route::post('/me/wishlist/{productId}',     [WishlistController::class, 'store'])->whereNumber('productId');
    Route::delete('/me/wishlist/{productId}',   [WishlistController::class, 'destroy'])->whereNumber('productId');
});

/*
|--------------------------------------------------------------------------
| Admin API — requires Sanctum token + admin role
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', AdminOnly::class])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('admin')->group(function () {
        // Products
        Route::get('/products', [ProductController::class, 'adminIndex']);
        Route::get('/products/{id}', [ProductController::class, 'adminShow'])->whereNumber('id');
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/{id}', [ProductController::class, 'update'])->whereNumber('id'); // multipart-friendly
        Route::put('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
        Route::patch('/products/{id}/stock', [ProductController::class, 'updateStock'])->whereNumber('id');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');

        // Categories
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update'])->whereNumber('id');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->whereNumber('id');

        // Reviews
        Route::get('/reviews', [ReviewController::class, 'adminIndex']);
        Route::post('/reviews/{id}/approve', [ReviewController::class, 'approve'])->whereNumber('id');
        Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->whereNumber('id');

        // Enquiries
        Route::get('/enquiries',                 [EnquiryController::class, 'adminIndex']);
        Route::put('/enquiries/{id}',            [EnquiryController::class, 'update'])->whereNumber('id');
        Route::post('/enquiries/{id}/resolve',   [EnquiryController::class, 'resolve'])->whereNumber('id');
        Route::delete('/enquiries/{id}',         [EnquiryController::class, 'destroy'])->whereNumber('id');

        // Offers
        Route::get('/offers', [OfferController::class, 'adminIndex']);
        Route::get('/offers/{id}', [OfferController::class, 'adminShow'])->whereNumber('id');
        Route::post('/offers', [OfferController::class, 'store']);
        Route::put('/offers/{id}', [OfferController::class, 'update'])->whereNumber('id');
        Route::delete('/offers/{id}', [OfferController::class, 'destroy'])->whereNumber('id');

        // Upcoming products
        Route::get('/upcoming', [UpcomingProductController::class, 'adminIndex']);
        Route::post('/upcoming', [UpcomingProductController::class, 'store']);
        Route::post('/upcoming/{id}', [UpcomingProductController::class, 'update'])->whereNumber('id'); // multipart
        Route::put('/upcoming/{id}', [UpcomingProductController::class, 'update'])->whereNumber('id');
        Route::delete('/upcoming/{id}', [UpcomingProductController::class, 'destroy'])->whereNumber('id');

        // Announcements (festival offers / coming-soon banners)
        Route::get('/announcements', [AnnouncementController::class, 'adminIndex']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::put('/announcements/{id}', [AnnouncementController::class, 'update'])->whereNumber('id');
        Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->whereNumber('id');

        // Notifications
        Route::get('/notifications',                 [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count',    [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read',      [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::post('/notifications/read-all',       [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}',         [NotificationController::class, 'destroy'])->whereNumber('id');

        // Customers (admin user management)
        Route::get('/customers',                       [CustomerController::class, 'index']);
        Route::get('/customers/{id}',                  [CustomerController::class, 'show'])->whereNumber('id');
        Route::put('/customers/{id}',                  [CustomerController::class, 'update'])->whereNumber('id');
        Route::patch('/customers/{id}/toggle-active',  [CustomerController::class, 'toggleActive'])->whereNumber('id');
        Route::delete('/customers/{id}',               [CustomerController::class, 'destroy'])->whereNumber('id');
    });
});
