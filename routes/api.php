<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Bets\CashoutController;
use App\Http\Controllers\Api\Fairness\GameFairnessController;
use App\Http\Controllers\Api\Fairness\RoundController;
use App\Http\Controllers\Api\Fairness\VerifyController;
use App\Http\Controllers\Api\OperatorApi\BetController;
use App\Http\Controllers\Api\OperatorApi\GameBetController;
use App\Http\Controllers\Api\OperatorApi\GameCatalogueController;
use App\Http\Controllers\Api\OperatorApi\LaunchController;
use App\Http\Controllers\Api\OperatorApi\PlayerSeedController;
use App\Http\Controllers\PlayApi\PlayController;
use App\Http\Middleware\AuthenticatePlaySession;
use App\Http\Middleware\EnforceIdempotency;
use App\Http\Middleware\VerifyOperatorSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The player API behind Orange's game and lobby pages, authenticated by the play session.
Route::prefix('play-api')
    ->middleware([AuthenticatePlaySession::class, 'throttle:120,1'])
    ->group(function (): void {
        Route::get('/session', [PlayController::class, 'session']);
        Route::get('/lobby', [PlayController::class, 'lobby']);
        Route::get('/games/{code}/bets', [PlayController::class, 'bets'])->where('code', '[a-z0-9_-]+');
        Route::post('/games/{code}/bets', [PlayController::class, 'bet'])->where('code', '[a-z0-9_-]+');
        Route::post('/bets/{bet}/cashout', [PlayController::class, 'cashout'])->whereNumber('bet');
        Route::get('/seeds', [PlayController::class, 'seeds']);
        Route::post('/seeds/rotate', [PlayController::class, 'rotateSeeds']);
    });

Route::prefix('v1')->group(function (): void {
    Route::post('/fairness/verify', VerifyController::class);

    // Public per-game fairness: what to verify with, and a verifier; and a round's details.
    Route::middleware('throttle:60,1')->group(function (): void {
        Route::get('/fairness/{code}', [GameFairnessController::class, 'show'])->where('code', '[a-z0-9_-]+');
        Route::get('/fairness/{code}/verify', [GameFairnessController::class, 'verify'])->where('code', '[a-z0-9_-]+');
        Route::get('/rounds/{uuid}', [RoundController::class, 'show'])->whereUuid('uuid');
    });

    Route::middleware([VerifyOperatorSignature::class, EnforceIdempotency::class])
        ->group(function (): void {
            Route::post('/ping', function (Request $request) {
                return response()->json([
                    'operator_id' => $request->attributes->get('operator')->id,
                    'echo' => $request->json()->all(),
                    'received_at' => now()->toIso8601String(),
                ], 201);
            });

            Route::post('/bets', [BetController::class, 'store']);
            Route::post('/bets/{bet}/cashout', CashoutController::class)->whereNumber('bet');

            // Multi-game API. The routes above stay exactly as they are for crash.
            Route::get('/games', GameCatalogueController::class);
            Route::post('/games/{code}/launch', [LaunchController::class, 'game'])->where('code', '[a-z0-9_-]+');
            Route::post('/games/{code}/bets', GameBetController::class)->where('code', '[a-z0-9_-]+');
            Route::post('/lobby/launch', [LaunchController::class, 'lobby']);
            Route::get('/players/{externalId}/seeds', [PlayerSeedController::class, 'show']);
            Route::post('/players/{externalId}/seeds/rotate', [PlayerSeedController::class, 'rotate']);
        });
});
