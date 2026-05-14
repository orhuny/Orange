<?php

declare(strict_types=1);

use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;
use App\Domain\ProvablyFair\Services\SeedRotator;

function rotator(): SeedRotator
{
    return new SeedRotator(new ProvablyFairService);
}

it('mints a new seed on first call to current()', function (): void {
    $r = rotator();

    expect(RngSeed::count())->toBe(0);

    $seed = $r->current();

    expect($seed->id)->toBeInt()
        ->and($seed->server_seed_hash)->toHaveLength(64)
        ->and($seed->isActive())->toBeTrue()
        ->and(RngSeed::count())->toBe(1);
});

it('returns the same active seed on subsequent calls', function (): void {
    $r = rotator();

    $a = $r->current();
    $b = $r->current();

    expect($a->id)->toBe($b->id);
});

it('rotate marks the active seed and creates a new one', function (): void {
    $r = rotator();
    $first = $r->current();

    $second = $r->rotate();

    expect($second->id)->not->toBe($first->id);

    $first->refresh();
    expect($first->rotated_at)->not->toBeNull()
        ->and($first->isActive())->toBeFalse()
        ->and($second->isActive())->toBeTrue();
});

it('reveal refuses to expose an active seed', function (): void {
    $r = rotator();
    $seed = $r->current();

    expect(fn () => $r->reveal($seed))->toThrow(RuntimeException::class);
});

it('reveal succeeds for a rotated seed and is idempotent', function (): void {
    $r = rotator();
    $first = $r->current();
    $r->rotate();
    $first->refresh();

    $revealed = $r->reveal($first);
    expect($revealed->revealed_at)->not->toBeNull();

    $revealedAgain = $r->reveal($revealed);
    expect($revealedAgain->revealed_at->equalTo($revealed->revealed_at))->toBeTrue();
});

it('trackRoundUsage rotates after threshold', function (): void {
    config()->set('provably_fair.seed_rotation_rounds', 3);
    $r = rotator();
    $seed = $r->current();
    $originalId = $seed->id;

    $afterFirst = $r->trackRoundUsage($seed);
    expect($afterFirst->id)->toBe($originalId)->and($afterFirst->used_round_count)->toBe(1);

    $afterSecond = $r->trackRoundUsage($afterFirst);
    expect($afterSecond->id)->toBe($originalId);

    $afterThird = $r->trackRoundUsage($afterSecond);
    expect($afterThird->id)->not->toBe($originalId)
        ->and($afterThird->isActive())->toBeTrue();
});
