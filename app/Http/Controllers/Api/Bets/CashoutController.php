<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bets;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Exceptions\AlreadyCashedOut;
use App\Domain\Game\Exceptions\RoundNotRunning;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Services\CashoutService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashoutController
{
    public function __construct(private readonly CashoutService $service) {}

    public function __invoke(Request $request, int $betId): JsonResponse
    {
        /** @var Operator $operator */
        $operator = $request->attributes->get('operator');

        $bet = Bet::query()
            ->where('id', $betId)
            ->where('operator_id', $operator->id)
            ->first();

        if (! $bet) {
            return response()->json(['error' => 'bet_not_found'], 404);
        }

        if ($bet->status !== BetStatus::Placed) {
            $existing = Cashout::query()->where('bet_id', $bet->id)->first();

            return response()->json([
                'error' => 'already_settled',
                'bet_status' => $bet->status->value,
                'cashout' => $existing ? $this->serialize($existing) : null,
            ], 409);
        }

        try {
            $cashout = $this->service->cashout($bet);
        } catch (AlreadyCashedOut) {
            return response()->json(['error' => 'already_cashed_out'], 409);
        } catch (RoundNotRunning $e) {
            return response()->json([
                'error' => 'round_not_running',
                'detail' => $e->getMessage(),
            ], 409);
        }

        return response()->json($this->serialize($cashout), 200);
    }

    /** @return array<string, mixed> */
    private function serialize(Cashout $cashout): array
    {
        return [
            'cashout_id' => $cashout->id,
            'bet_id' => $cashout->bet_id,
            'multiplier' => (float) $cashout->multiplier,
            'payout_amount' => (string) $cashout->payout_amount,
            'cashed_out_at' => $cashout->cashed_out_at?->toIso8601String(),
            'is_auto' => $cashout->is_auto,
        ];
    }
}
