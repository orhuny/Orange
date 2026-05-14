<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum WebhookStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case DeadLetter = 'dlq';
}
