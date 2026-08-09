<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigApiController;
use App\Http\Controllers\Api\ExamApiController;
use App\Http\Controllers\Api\OmrApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Authenticated routes
    Route::middleware([
        'auth:sanctum',
        'active_account',
        'verified_account',
        'password_changed',
    ])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Exams for the OMR scanner contain answer keys and student identifiers.
        Route::middleware('omr_api')->group(function () {
            Route::get('exams', [ExamApiController::class, 'index']);
            Route::get('exams/{exam}/download', [ExamApiController::class, 'download']);
        });

        // OMR Scans
        Route::prefix('omr/scans')->middleware('omr_api')->group(function () {
            Route::get('/', [OmrApiController::class, 'index']);
            Route::post('/', [OmrApiController::class, 'store']);
            Route::get('pages/{page}/image', [OmrApiController::class, 'pageImage']);
            Route::get('{scan}', [OmrApiController::class, 'show']);
            Route::put('{scan}/confirm', [OmrApiController::class, 'confirm']);
            Route::put('{scan}/reject', [OmrApiController::class, 'reject']);
        });
    });
});

/* ── API v2 — Config + Enhanced OMR ── */

Route::prefix('v2')->group(function () {
    Route::middleware([
        'auth:sanctum',
        'active_account',
        'verified_account',
        'password_changed',
    ])->group(function () {
        // Configuration (Duoscanner reads effective config, Platform manages rules)
        Route::prefix('config')->group(function () {
            Route::get('effective', [ConfigApiController::class, 'effective']);

            Route::middleware('role:institution_admin|global_admin')->group(function () {
                Route::get('trace/{userId}', [ConfigApiController::class, 'trace']);
                Route::get('audit', [ConfigApiController::class, 'audit']);
                Route::get('rules', [ConfigApiController::class, 'indexRules']);
                Route::post('rules', [ConfigApiController::class, 'storeRule']);
                Route::put('rules/{id}', [ConfigApiController::class, 'updateRule']);
                Route::delete('rules/{id}', [ConfigApiController::class, 'destroyRule']);
            });
        });

        // Answer sheet types & scan modes (read-only for app, managed via admin)
        Route::get('answer-sheet-types', [ConfigApiController::class, 'answerSheetTypes']);
        Route::get('scan-modes', [ConfigApiController::class, 'scanModes']);

        // Enhanced Exam download (v2 includes version + signed QR)
        Route::get('exams/{exam}/download', [ExamApiController::class, 'download'])
            ->middleware('omr_api');

        // OMR Scans (v2, same logic enhanced)
        Route::prefix('omr/scans')->middleware('omr_api')->group(function () {
            Route::get('/', [OmrApiController::class, 'index']);
            Route::post('/', [OmrApiController::class, 'store']);
            Route::get('pages/{page}/image', [OmrApiController::class, 'pageImage']);
            Route::get('{scan}', [OmrApiController::class, 'show']);
            Route::put('{scan}/confirm', [OmrApiController::class, 'confirm']);
            Route::put('{scan}/reject', [OmrApiController::class, 'reject']);
        });
    });
});
