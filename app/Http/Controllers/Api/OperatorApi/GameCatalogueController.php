<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Game\Services\GameCatalogue;
use App\Domain\Operator\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /v1/games — the games the calling operator can offer, with its effective limits. */
class GameCatalogueController
{
    public function __construct(private readonly GameCatalogue $catalogue) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Operator $operator */
        $operator = $request->attributes->get('operator');

        return response()->json(['data' => $this->catalogue->forOperator($operator)]);
    }
}
