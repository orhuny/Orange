<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Bets\CashoutController;
use App\Http\Controllers\Api\Fairness\VerifyController;
use App\Http\Controllers\Api\OperatorApi\BetController;
use App\Http\Middleware\EnforceIdempotency;
use App\Http\Middleware\VerifyOperatorSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/fairness/verify', VerifyController::class);

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
        });
});
