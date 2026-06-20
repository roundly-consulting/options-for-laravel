<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Options\Commands\Concerns\ResolvesOwner;
use RoundlyConsulting\Options\Exceptions\OptionException;
use RoundlyConsulting\Options\OptionsManager;

final class ListOptionsCommand extends Command
{
    use ResolvesOwner;

    protected $signature = 'options:list
        {--owner= : Owner model class}
        {--owner-id= : Owner model id}';

    protected $description = 'List registered options and their resolved values';

    public function handle(OptionsManager $manager): int
    {
        try {
            $owner = $this->resolveOwner();
        } catch (OptionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $registered = $manager->registered();

        if ($registered === []) {
            $this->warn('No options are registered. Register options via Options::register([...]).');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($registered as $key => $class) {
            $option = $manager->resolve($class, $owner);

            $rows[] = [
                $key,
                $option->readable(),
                $this->stringify($option->value()),
            ];
        }

        $this->table(['Key', 'Readable', 'Value'], $rows);

        return self::SUCCESS;
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }
}
