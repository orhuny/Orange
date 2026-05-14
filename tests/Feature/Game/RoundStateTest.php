<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;

it('only allows the documented forward transitions', function (): void {
    expect(RoundState::Waiting->canTransitionTo(RoundState::Betting))->toBeTrue()
        ->and(RoundState::Waiting->canTransitionTo(RoundState::Running))->toBeFalse()
        ->and(RoundState::Betting->canTransitionTo(RoundState::Running))->toBeTrue()
        ->and(RoundState::Betting->canTransitionTo(RoundState::Crashed))->toBeFalse()
        ->and(RoundState::Running->canTransitionTo(RoundState::Crashed))->toBeTrue()
        ->and(RoundState::Crashed->canTransitionTo(RoundState::Settled))->toBeTrue()
        ->and(RoundState::Settled->canTransitionTo(RoundState::Waiting))->toBeFalse();
});

it('exposes betting/cashout/terminal predicates correctly', function (): void {
    expect(RoundState::Betting->acceptsBets())->toBeTrue()
        ->and(RoundState::Running->acceptsBets())->toBeFalse()
        ->and(RoundState::Running->acceptsCashout())->toBeTrue()
        ->and(RoundState::Crashed->acceptsCashout())->toBeFalse()
        ->and(RoundState::Settled->isTerminal())->toBeTrue()
        ->and(RoundState::Waiting->isTerminal())->toBeFalse();
});
