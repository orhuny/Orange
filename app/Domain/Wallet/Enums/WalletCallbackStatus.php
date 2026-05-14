<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum WalletCallbackStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Mocked = 'mocked';
}
