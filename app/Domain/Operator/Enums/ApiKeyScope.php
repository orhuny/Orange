<?php

declare(strict_types=1);

namespace App\Domain\Operator\Enums;

enum ApiKeyScope: string
{
    case SessionsWrite = 'sessions:write';
    case BetsWrite = 'bets:write';
    case CashoutsWrite = 'cashouts:write';
    case ReportsRead = 'reports:read';
}
