<?php

declare(strict_types=1);

namespace App\Domain\Game\Enums;

enum RoundState: string
{
    case Waiting = 'waiting';
    case Betting = 'betting';
    case Running = 'running';
    case Crashed = 'crashed';
    case Settled = 'settled';

    /**
     * Allowed forward transitions per state.
     *
     * @return array<int, RoundState>
     */
    public function next(): array
    {
        return match ($this) {
            self::Waiting => [self::Betting],
            self::Betting => [self::Running],
            self::Running => [self::Crashed],
            self::Crashed => [self::Settled],
            self::Settled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->next(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Settled;
    }

    public function acceptsBets(): bool
    {
        return $this === self::Betting;
    }

    public function acceptsCashout(): bool
    {
        return $this === self::Running;
    }
}
