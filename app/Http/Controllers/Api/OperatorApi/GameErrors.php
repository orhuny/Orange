<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Game\Exceptions\BettingClosed;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * The operator API's error responses for game rules, shared by the legacy /v1/bets and
 * the multi-game endpoints so both answer the same way. Every one of them is a 4xx that
 * leaves no bet and no debit behind (operators such as afq refund on any 4xx).
 */
final class GameErrors
{
    public static function render(Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof BettingClosed => response()->json(['error' => 'betting_closed', 'message' => $e->getMessage()], 409),
            $e instanceof GameNotAvailable => response()->json(
                ['error' => $e->reason, 'message' => $e->getMessage()],
                $e->reason === 'game_not_enabled' ? 403 : 409,
            ),
            $e instanceof CurrencyNotSupported => response()->json(['error' => 'currency_not_supported', 'message' => $e->getMessage()], 422),
            $e instanceof StakeOutsideLimits => response()->json(['error' => $e->reason, 'message' => $e->getMessage(), 'limit' => $e->limit], 422),
            $e instanceof InvalidBetParams => response()->json(['error' => 'invalid_params', 'message' => $e->getMessage()], 422),
            default => null,
        };
    }

    public static function unknownGame(): JsonResponse
    {
        return response()->json(['error' => 'unknown_game'], 404);
    }
}
