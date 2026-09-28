<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RoundlyConsulting\Options\Commands\Concerns\ResolvesOwner;
use RoundlyConsulting\Options\Exceptions\OptionException;
use RoundlyConsulting\Options\OptionsManager;

final class ExportOptionsCommand extends Command
{
    use ResolvesOwner;

    protected $signature = 'options:export
        {--owner= : Owner model class}
        {--owner-id= : Owner model id}
        {--path= : Write the JSON export to this file path}';

    protected $description = 'Export stored options as JSON';

    public function handle(OptionsManager $options): int
    {
        try {
            $owner = $this->resolveOwner();
        } catch (OptionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $json = $options->exportJson($owner);

        /** @var string|null $path */
        $path = $this->option('path');

        if ($path !== null) {
            File::put($path, $json);
            $this->info("Options exported to {$path}.");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
