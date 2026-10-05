<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

final class MakeOptionCommand extends GeneratorCommand
{
    /** @var string */
    protected $name = 'make:option';

    /** @var string */
    protected $description = 'Create a new option class';

    /** @var string */
    protected $type = 'Option';

    protected function getStub(): string
    {
        if ($this->option('enum')) {
            return __DIR__.'/stubs/option.enum.stub';
        }

        return __DIR__.'/stubs/option.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Options';
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $key = $this->option('key');
        $key = is_string($key) && $key !== '' ? $key : class_basename($name);

        $cast = $this->option('cast');
        $cast = is_string($cast) && $cast !== '' ? $cast : 'string';

        // Both land inside single-quoted PHP strings in the stub.
        $stub = str_replace(['{{ key }}', '{{key}}'], addcslashes($key, "'\\"), $stub);
        $stub = str_replace(['{{ cast }}', '{{cast}}'], addcslashes($cast, "'\\"), $stub);

        $enum = $this->option('enum');
        $enumClass = is_string($enum) ? $enum : '';
        $stub = str_replace(['{{ enum }}', '{{enum}}'], $enumClass, $stub);
        $stub = str_replace(['{{ enumBasename }}', '{{enumBasename}}'], class_basename($enumClass), $stub);

        $encrypted = $this->option('encrypted') ? 'true' : 'false';
        $stub = str_replace(['{{ encrypted }}', '{{encrypted}}'], $encrypted, $stub);

        return $stub;
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['cast', null, InputOption::VALUE_OPTIONAL, 'The value cast (e.g. string, integer, collection)', 'string'],
            ['enum', null, InputOption::VALUE_OPTIONAL, 'A backed-enum class to cast to'],
            ['encrypted', null, InputOption::VALUE_NONE, 'Encrypt the value at rest'],
            ['key', null, InputOption::VALUE_OPTIONAL, 'The option key (defaults to the class name)'],
        ];
    }
}
