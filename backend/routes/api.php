<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['name' => 'sunnaa-ai2-api', 'ok' => true]);

/* ---------- public (visitors) ---------- */
Route::post('auth/link', [AuthController::class, 'link'])->middleware('throttle:6,1');
Route::post('auth/verify', [AuthController::class, 'verify'])->middleware('throttle:20,1');

Route::get('profiles', [PublicController::class, 'profiles']);
Route::get('works', [PublicController::class, 'works']);
Route::get('exercises', [PublicController::class, 'exercises']);
Route::get('reactions', [ReactionController::class, 'index']);
Route::get('comments/rows', [CommentController::class, 'rows']);
Route::get('works/{work}/comments', [CommentController::class, 'index']);

Route::get('files/works/{work}', [FileController::class, 'work'])->name('files.work');
Route::get('files/avatars/{profile}', [FileController::class, 'avatar'])->name('files.avatar');

/* ---------- signed in ---------- */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('join', JoinController::class);
    Route::delete('me', [ProfileController::class, 'destroy']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'read']);

    // owner or admin (checked inside)
    Route::match(['post', 'patch'], 'works/{work}', [WorkController::class, 'update']);
    Route::delete('works/{work}', [WorkController::class, 'destroy']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    /* members only (joined with an invite) */
    Route::middleware('member')->group(function () {
        Route::post('me/profile', [ProfileController::class, 'update']);
        Route::post('works', [WorkController::class, 'store']);
        Route::put('works/{work}/reaction', [ReactionController::class, 'upsert']);
        Route::post('works/{work}/comments', [CommentController::class, 'store']);
    });

    /* admins only */
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('invites', [AdminController::class, 'invites']);
        Route::post('invites', [AdminController::class, 'createInvite']);
        Route::delete('invites/{invite}', [AdminController::class, 'deleteInvite']);
        Route::delete('members/{profile}', [AdminController::class, 'deleteMember']);
        Route::put('exercises/{no}', [AdminController::class, 'saveExercise'])->whereNumber('no');
    });
});
