<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\Command;
use JsonException;
use RoundlyConsulting\Options\Commands\Concerns\ActsAsUser;
use RoundlyConsulting\Options\Commands\Concerns\ResolvesOwner;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;
use RoundlyConsulting\Options\Exceptions\OptionException;
use RoundlyConsulting\Options\OptionsManager;

final class SetOptionCommand extends Command
{
    use ActsAsUser;
    use ResolvesOwner;

    protected $signature = 'options:set {option : Registered key or option class-string}
        {value : The value to store}
        {--owner= : Owner model class}
        {--owner-id= : Owner model id}
        {--json : Decode the value as JSON before storing}
        {--as= : Authorize as the user with this key (default: bypass auth)}';

    protected $description = 'Persist a value for an option';

    public function handle(OptionsManager $manager): int
    {
        /** @var string $option */
        $option = $this->argument('option');
        /** @var string $value */
        $value = $this->argument('value');

        try {
            $owner = $this->resolveOwner();
            $this->withAuthorizationContext($manager, function () use ($manager, $option, $value, $owner): void {
                $manager->set($option, $this->castValue($value), $owner);
            });
        } catch (OptionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Option [{$option}] updated.");

        return self::SUCCESS;
    }

    private function castValue(string $value): mixed
    {
        if (! $this->option('json')) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidOptionPayload::message('The value is not valid JSON: '.$exception->getMessage());
        }
    }
}
