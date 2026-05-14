<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum WalletTransactionType: string
{
    case Debit = 'debit';
    case Credit = 'credit';
    case Rollback = 'rollback';
}
