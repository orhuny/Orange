<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines;

use App\Domain\Game\Engines\Contracts\GameEngine;
use App\Domain\Game\Engines\Contracts\RoundBasedEngine;
use App\Domain\Game\Exceptions\UnknownGameType;
use App\Domain\Game\Models\Game;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Maps games.type to its engine, from config('games.engines'). Adding a game type is
 * one line there (see docs/adding-a-game.md); nothing in the core changes.
 */
final class EngineRegistry
{
    /** @var array<string, GameEngine> */
    private array $resolved = [];

    /** @param  array<string, class-string<GameEngine>>  $engines  games.type => engine class */
    public function __construct(
        private readonly Container $container,
        private readonly array $engines,
    ) {}

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->engines);
    }

    public function has(string $type): bool
    {
        return isset($this->engines[$type]);
    }

    public function for(string $type): GameEngine
    {
        if (isset($this->resolved[$type])) {
            return $this->resolved[$type];
        }

        $class = $this->engines[$type] ?? throw UnknownGameType::named($type);
        $engine = $this->container->make($class);

        if (! $engine instanceof GameEngine || $engine::type() !== $type) {
            throw new LogicException("config/games.php maps game type [{$type}] to [{$class}], which is not that type's engine.");
        }

        return $this->resolved[$type] = $engine;
    }

    public function forGame(Game $game): GameEngine
    {
        return $this->for($game->engineType());
    }

    public function roundBased(Game $game): RoundBasedEngine
    {
        $engine = $this->forGame($game);

        if (! $engine instanceof RoundBasedEngine) {
            throw new LogicException("Game [{$game->code}] is not a round-based game.");
        }

        return $engine;
    }
}
