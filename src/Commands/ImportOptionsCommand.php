<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RoundlyConsulting\Options\Actions\ImportOptionsAction;
use RoundlyConsulting\Options\Exceptions\OptionException;

final class ImportOptionsCommand extends Command
{
    protected $signature = 'options:import {path : Path to a JSON export file}';

    protected $description = 'Import options from a JSON export file';

    public function handle(ImportOptionsAction $action): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $count = $action->fromJson(File::get($path));
        } catch (OptionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$count} option(s).");

        return self::SUCCESS;
    }
}
