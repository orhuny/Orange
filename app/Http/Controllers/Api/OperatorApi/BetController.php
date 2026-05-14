<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Game\Exceptions\BettingClosed;
use App\Domain\Game\Services\BetPlacementService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetController
{
    public function __construct(private readonly BetPlacementService $bets) {}

    public function store(Request $request): JsonResponse
    {
        /** @var Operator $operator */
        $operator = $request->attributes->get('operator');

        $validated = $request->validate([
            'player_external_id' => ['required', 'string', 'min:1', 'max:128'],
            'round_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'auto_cashout_at' => ['nullable', 'numeric', 'gte:1.01'],
        ]);

        try {
            $bet = $this->bets->place(
                operator: $operator,
                externalPlayerId: $validated['player_external_id'],
                roundUuid: $validated['round_uuid'],
                amount: (string) $validated['amount'],
                currency: strtoupper($validated['currency']),
                idempotencyKey: (string) $request->header('Idempotency-Key'),
                autoCashoutAt: $validated['auto_cashout_at'] ?? null,
            );
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'unknown_round'], 404);
        } catch (BettingClosed $e) {
            return response()->json(['error' => 'betting_closed', 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'bet_id' => $bet->id,
            'status' => $bet->status->value,
            'round_uuid' => $bet->round->round_uuid,
            'amount' => (string) $bet->amount,
            'currency' => $bet->currency,
            'wallet_tx_id' => $bet->external_tx_id,
        ], 201);
    }
}
