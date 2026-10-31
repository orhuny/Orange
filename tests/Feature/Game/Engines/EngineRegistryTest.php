<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Exceptions\UnknownGameType;
use App\Domain\Game\Models\Game;

it('resolves every existing game to the crash engine', function (): void {
    $registry = app(EngineRegistry::class);
    $game = Game::factory()->create();

    expect($game->engineType())->toBe('crash')
        ->and($registry->forGame($game))->toBeInstanceOf(CrashEngine::class)
        ->and($registry->roundBased($game))->toBeInstanceOf(CrashEngine::class)
        ->and($registry->types())->toBe(['crash']);
});

it('returns one engine instance per type', function (): void {
    $registry = app(EngineRegistry::class);

    expect($registry->for('crash'))->toBe($registry->for('crash'));
});

it('refuses an unknown game type', function (): void {
    expect(app(EngineRegistry::class)->has('dice'))->toBeFalse()
        ->and(fn () => app(EngineRegistry::class)->for('dice'))->toThrow(UnknownGameType::class);
});

it('refuses a config entry that maps a type to another type\'s engine', function (): void {
    $registry = new EngineRegistry(app(), ['dice' => CrashEngine::class]);

    expect(fn () => $registry->for('dice'))->toThrow(LogicException::class);
});
