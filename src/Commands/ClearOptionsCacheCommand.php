<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Options\OptionsManager;

final class ClearOptionsCacheCommand extends Command
{
    protected $signature = 'options:clear-cache';

    protected $description = 'Flush the in-request and persistent option caches';

    public function handle(OptionsManager $manager): int
    {
        $manager->flushCache();

        $this->info('Option caches cleared.');

        return self::SUCCESS;
    }
}
