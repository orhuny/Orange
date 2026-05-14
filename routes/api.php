<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceIdempotency;
use App\Http\Middleware\VerifyOperatorSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([VerifyOperatorSignature::class, EnforceIdempotency::class])
    ->group(function (): void {
        Route::post('/ping', function (Request $request) {
            return response()->json([
                'operator_id' => $request->attributes->get('operator')->id,
                'echo' => $request->json()->all(),
                'received_at' => now()->toIso8601String(),
            ], 201);
        });
    });
