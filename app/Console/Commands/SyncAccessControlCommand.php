<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AccessControl;
use Illuminate\Console\Command;

class SyncAccessControlCommand extends Command
{
    protected $signature = 'orange:sync-access';

    protected $description = 'Create any admin-panel permissions/starter roles missing from the database (existing roles are left as edited)';

    public function handle(): int
    {
        AccessControl::sync();
        $this->info('Permissions and starter roles are in sync.');

        return self::SUCCESS;
    }
}
