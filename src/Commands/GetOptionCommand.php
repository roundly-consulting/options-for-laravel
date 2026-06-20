<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Options\Commands\Concerns\ActsAsUser;
use RoundlyConsulting\Options\Commands\Concerns\ResolvesOwner;
use RoundlyConsulting\Options\Exceptions\OptionException;
use RoundlyConsulting\Options\OptionsManager;

final class GetOptionCommand extends Command
{
    use ActsAsUser;
    use ResolvesOwner;

    protected $signature = 'options:get {option : Registered key or option class-string}
        {--owner= : Owner model class}
        {--owner-id= : Owner model id}
        {--as= : Authorize as the user with this key (default: bypass auth)}';

    protected $description = 'Print the resolved value of an option';

    public function handle(OptionsManager $manager): int
    {
        /** @var string $option */
        $option = $this->argument('option');

        try {
            $owner = $this->resolveOwner();
            $value = $this->withAuthorizationContext($manager, fn (): mixed => $manager->get($option, $owner));
        } catch (OptionException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($this->stringify($value));

        return self::SUCCESS;
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
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
