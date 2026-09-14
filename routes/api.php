<?php

use App\Http\Controllers\Api\ChamberCaptureController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Sanctum token guard for external API consumers only.
| The Inertia UI authenticates via Fortify session cookies.
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('/chamber/recording', [ChamberCaptureController::class, 'state'])->name('api.chamber.recording');
    Route::post('/chamber/heartbeat', [ChamberCaptureController::class, 'heartbeat'])->name('api.chamber.heartbeat');
    Route::post('/sessions/{session}/chamber/chunks', [ChamberCaptureController::class, 'storeChunk'])
        ->name('api.chamber.chunks.store');
});
