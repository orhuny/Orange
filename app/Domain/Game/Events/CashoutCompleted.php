<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\Cashout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashoutCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Cashout $cashout) {}
}
