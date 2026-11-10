<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['name' => 'sunnaa-ai2-api', 'ok' => true]);

/* ---------- public (visitors) ---------- */
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('auth/forgot', [AuthController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('auth/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1');

Route::get('profiles', [PublicController::class, 'profiles']);
Route::get('works', [PublicController::class, 'works']);
Route::get('exercises', [PublicController::class, 'exercises']);
Route::get('reactions', [ReactionController::class, 'index']);
Route::get('comments/rows', [CommentController::class, 'rows']);
Route::get('works/{work}/comments', [CommentController::class, 'index']);

Route::get('files/works/{work}', [FileController::class, 'work'])->name('files.work');          // first file (older links)
Route::get('files/work-files/{file}', [FileController::class, 'workFile'])->name('files.workfile');
Route::get('files/avatars/{profile}', [FileController::class, 'avatar'])->name('files.avatar');

/* ---------- signed in ---------- */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');
    Route::put('me/username', [AuthController::class, 'changeUsername']);
    Route::delete('me', [ProfileController::class, 'destroy']);

    Route::get('notifications', [NotificationController::class, 'index']);

    // grades: own works (admins: all) · Claude reviews: owner or admin · assistant: members and admins
    Route::get('grades', [GradeController::class, 'index']);
    Route::get('works/{work}/reviews', [ReviewController::class, 'index']);
    Route::post('works/{work}/reviews', [ReviewController::class, 'store'])->middleware('throttle:6,1');
    Route::get('materials', [AssistantController::class, 'materials']);
    Route::post('assistant', [AssistantController::class, 'ask'])->middleware('throttle:10,1');
    Route::post('notifications/read', [NotificationController::class, 'read']);

    // owner or admin (checked inside)
    Route::match(['post', 'patch'], 'works/{work}', [WorkController::class, 'update']);
    Route::delete('works/{work}', [WorkController::class, 'destroy']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    /* members only (approved, with a public page) */
    Route::middleware('member')->group(function () {
        Route::post('me/profile', [ProfileController::class, 'update']);
        Route::post('works', [WorkController::class, 'store']);
        Route::put('works/{work}/reaction', [ReactionController::class, 'upsert']);
        Route::post('works/{work}/comments', [CommentController::class, 'store']);
    });

    /* admins only */
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('requests', [AdminController::class, 'requests']);
        Route::post('requests/{user}/approve', [AdminController::class, 'approve']);
        Route::post('requests/{user}/reject', [AdminController::class, 'reject']);
        Route::delete('members/{profile}', [AdminController::class, 'deleteMember']);
        Route::put('exercises/{no}', [AdminController::class, 'saveExercise'])->whereNumber('no');
        Route::put('works/{work}/grade', [GradeController::class, 'upsert']);
        Route::delete('works/{work}/grade', [GradeController::class, 'destroy']);
        Route::post('materials', [AssistantController::class, 'store']);
        Route::put('materials/{material}', [AssistantController::class, 'update']);
        Route::delete('materials/{material}', [AssistantController::class, 'destroy']);
    });
});
