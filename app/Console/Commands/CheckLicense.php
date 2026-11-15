<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

class CheckLicense extends Command
{
    protected $signature = 'license:check';

    protected $description = 'Ask the license server whether this site\'s serial key is still active';

    public function handle(): int
    {
        if (! LicenseService::enabled() || LicenseService::isDevInstall() || ! LicenseService::serial()) {
            $this->line('License check skipped (lock off, local install or no key).');

            return self::SUCCESS;
        }
        $r = LicenseService::refresh();
        $this->line($r['ok'] ? 'License active.' : 'License problem: '.$r['message']);

        return self::SUCCESS;
    }
}
