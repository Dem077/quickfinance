<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Create missing Shield permissions and grant them to super_admin';

    public function handle(): int
    {
        $result = PermissionCatalog::sync();

        $this->info("Created {$result['created']} permission(s).");
        $this->info("Granted {$result['granted']} permission(s) to super_admin.");

        return self::SUCCESS;
    }
}
