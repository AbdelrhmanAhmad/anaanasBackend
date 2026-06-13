<?php

use App\Http\Controllers\Api\v1\AccountVerificationController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\AuctionController;
use App\Http\Controllers\Api\v1\HomeSliderController;
use App\Http\Controllers\Api\v1\HomeStatsController;
use App\Http\Controllers\Api\v1\ChatController;
use App\Http\Controllers\Api\v1\CommentController;
use App\Http\Controllers\Api\v1\ContactController;
use App\Http\Controllers\Api\v1\CommentReactionController;
use App\Http\Controllers\Api\v1\EmailVerificationController;
use App\Http\Controllers\Api\v1\FollowController;
use App\Http\Controllers\Api\v1\MessageController;
use App\Http\Controllers\Api\v1\NotificationController;
use App\Http\Controllers\Api\v1\PostController;
use App\Http\Controllers\Api\v1\PostEventController;
use App\Http\Controllers\Api\v1\PostReactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('home/section-momentum', [HomeStatsController::class, 'sectionMomentum']);
Route::get('home/trending-posts', [HomeStatsController::class, 'trendingPosts']);
Route::get('home/latest-listings', [HomeStatsController::class, 'latestListings']);
Route::get('home/sliders', [HomeSliderController::class, 'index']);

Route::get("sections", [\App\Http\Controllers\Api\v1\SectionController::class, 'index']);
Route::get("sections/categories", [\App\Http\Controllers\Api\v1\SectionController::class, 'SectionCategories']);
Route::get("sections/categories/fet-fields", [\App\Http\Controllers\Api\v1\SectionController::class, 'fields']);
Route::get("sections/categories/fet-subfields", [\App\Http\Controllers\Api\v1\SectionController::class, 'subfields']);


Route::get("countries", [\App\Http\Controllers\Api\v1\SectionController::class, 'countries']);
Route::get("cities", [\App\Http\Controllers\Api\v1\SectionController::class, 'cities']);

Route::get('sitemap/countries', [\App\Http\Controllers\Api\v1\SitemapController::class, 'countries']);
Route::get('sitemap/sections', [\App\Http\Controllers\Api\v1\SitemapController::class, 'sections']);
Route::get('sitemap/cities', [\App\Http\Controllers\Api\v1\SitemapController::class, 'cities']);
Route::get('sitemap/posts', [\App\Http\Controllers\Api\v1\SitemapController::class, 'posts']);
Route::get('sitemap/cache/{type}/{iso2}', [\App\Http\Controllers\Api\v1\SitemapController::class, 'cacheFile'])
    ->whereIn('type', ['sections', 'cities'])
    ->where('iso2', '[a-z]{2,3}');

Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact');

// Follow status — public (returns is_following=false for guests)
Route::get('sections/{section}/follow', [FollowController::class, 'sectionStatus'])->whereNumber('section');
Route::get('categories/{category}/follow', [FollowController::class, 'categoryStatus'])->whereNumber('category');


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('post/creation-limit', [\App\Http\Controllers\Api\v1\SectionController::class, 'creationLimit']);
    Route::get('account/verification-status', [AccountVerificationController::class, 'status']);
    Route::post('account/verification-request', [AccountVerificationController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post("post", [\App\Http\Controllers\Api\v1\SectionController::class, 'post']);
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->whereNumber('post');
    Route::post('posts/{post}/reactions', [PostReactionController::class, 'toggle'])->whereNumber('post');
    Route::post('comments/{comment}/reactions', [CommentReactionController::class, 'toggle'])->whereNumber('comment');
    Route::get('posts/{post}/chat', [ChatController::class, 'getOrCreate'])->whereNumber('post');
    Route::post('chats/{chat}/messages', [MessageController::class, 'store']);
});

Route::get("posts", [\App\Http\Controllers\Api\v1\SectionController::class, 'getPosts']);
Route::get('auctions', [AuctionController::class, 'index']);
Route::get('auctions/{post}', [AuctionController::class, 'show'])->whereNumber('post');

Route::get('posts/{post}/comments', [CommentController::class, 'index']);
Route::get('posts/{post}/reactions', [PostReactionController::class, 'summary']);
Route::post('posts/{post}/events', [PostEventController::class, 'store']);
Route::get('posts/{post}/similar', [PostController::class, 'similar'])->whereNumber('post');
Route::get('posts/{post}/more-from-section', [PostController::class, 'moreFromSection'])->whereNumber('post');
Route::get('posts/{post}', [PostController::class, 'show'])->whereNumber('post');

Route::get('comments/{comment}/replies', [CommentController::class, 'replies']);
Route::get('comments/{comment}/reactions', [CommentReactionController::class, 'summary']);

// Chat routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auctions', [AuctionController::class, 'store']);
    Route::post('auctions/{post}', [AuctionController::class, 'update'])->whereNumber('post');
    Route::delete('auctions/{post}', [AuctionController::class, 'destroy'])->whereNumber('post');
    Route::post('auctions/{post}/bid', [AuctionController::class, 'bid'])->whereNumber('post');
    Route::post('auctions/{post}/watch', [AuctionController::class, 'toggleWatch'])->whereNumber('post');
    Route::get('auctions/{post}/statistics', [AuctionController::class, 'statistics'])->whereNumber('post');
    Route::get('auctions/my-posts', [AuctionController::class, 'myAuctions']);

    // List all chats for authenticated user
    Route::get('chats', [ChatController::class, 'index']);
    // Get specific chat
    Route::get('chats/{chat}', [ChatController::class, 'show']);
    // Mark chat as read
    Route::post('chats/{chat}/read', [ChatController::class, 'markAsRead']);
    // Delete chat (per-user soft delete)
    Route::delete('chats/{chat}', [ChatController::class, 'delete']);
    // Per-user clear-history cutoff
    Route::post('chats/{chat}/clear', [ChatController::class, 'clear']);
    // Close conversation (read-only for both)
    Route::post('chats/{chat}/close', [ChatController::class, 'close']);
    Route::post('chats/{chat}/reopen', [ChatController::class, 'reopen']);
    // Block / unblock the other participant
    Route::post('chats/{chat}/block', [ChatController::class, 'block']);
    Route::post('chats/{chat}/unblock', [ChatController::class, 'unblock']);
    // Report conversation for admin review
    Route::post('chats/{chat}/report', [ChatController::class, 'report']);
    // Typing indicator (lightweight — broadcast via WS)
    Route::post('chats/{chat}/typing', [ChatController::class, 'typing']);

    // Messages
    Route::get('chats/{chat}/messages', [MessageController::class, 'index']);
    Route::post('chats/{chat}/messages/read', [MessageController::class, 'markAsRead']);

    // Update post (owner only)
    Route::post('posts/{post}', [PostController::class, 'update'])->whereNumber('post');
    Route::delete('posts/{post}', [PostController::class, 'delete'])->whereNumber('post');
    // Delete post image (owner only)
    Route::delete('posts/{post}/images/{image}', [PostController::class, 'deleteImage'])
        ->whereNumber('post')
        ->whereNumber('image');
    // Post statistics (owner only)
    Route::get('posts/{post}/statistics', [PostController::class, 'statistics'])->whereNumber('post');
    // Get authenticated user's overall statistics
    Route::get('posts/my-statistics', [PostController::class, 'myStatistics']);
    // Get user's posts
    Route::get('posts/my-posts', [PostController::class, 'myPosts']);
    // Get user's post images
    Route::get('posts/my-images', [PostController::class, 'myImages']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Follows (toggle endpoints require authentication; status is public above)
    Route::post('sections/{section}/follow', [FollowController::class, 'toggleSection'])->whereNumber('section');
    Route::post('categories/{category}/follow', [FollowController::class, 'toggleCategory'])->whereNumber('category');
    Route::get('follows', [FollowController::class, 'myFollows']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1'); // 5 attempts per minute
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute - brute force protection
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',     [AuthController::class, 'me']);
        Route::post('/logout',[AuthController::class, 'logout']);
        // Accept both PUT and POST: PHP does not populate $_FILES on PUT multipart
        // requests, so the client POSTs multipart uploads to the same endpoint.
        Route::match(['put', 'post'], '/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/request-account-deletion', [AuthController::class, 'requestAccountDeletion']);
        Route::post('/cancel-account-deletion', [AuthController::class, 'cancelAccountDeletion']);

        Route::prefix('email')->group(function () {
            Route::get('/status', [EmailVerificationController::class, 'status']);
            Route::post('/send', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:email-verify-send');
            Route::post('/verify', [EmailVerificationController::class, 'verify'])
                ->middleware('throttle:email-verify-attempt');
            Route::post('/change', [EmailVerificationController::class, 'changeEmail'])
                ->middleware('throttle:email-verify-send');
        });
    });
});
