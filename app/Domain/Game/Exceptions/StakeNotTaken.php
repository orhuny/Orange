<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use RuntimeException;

/** Direct play: the operator did not confirm the stake, so the bet does not count. */
class StakeNotTaken extends RuntimeException
{
    /**
     * @param  bool  $definite  true: the operator refused (nothing taken); false: no answer (rolled back)
     * @param  string|null  $operatorError  the operator's own error code, e.g. insufficient_funds
     */
    public function __construct(string $message, public readonly bool $definite, public readonly ?string $operatorError = null)
    {
        parent::__construct($message);
    }

    public static function refused(?string $operatorError): self
    {
        return new self('The operator refused the stake.', true, $operatorError);
    }

    public static function unanswered(): self
    {
        return new self('The operator wallet did not answer; the stake has been rolled back.', false);
    }
}
