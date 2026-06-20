<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

final class MakeOptionGroupCommand extends GeneratorCommand
{
    /** @var string */
    protected $name = 'make:option-group';

    /** @var string */
    protected $description = 'Create a new option group class';

    /** @var string */
    protected $type = 'OptionGroup';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/option-group.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Settings';
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $label = str(class_basename($name))->headline()->toString();
        $stub = str_replace(['{{ label }}', '{{label}}'], $label, $stub);

        $stub = str_replace(['{{ options }}', '{{options}}'], $this->optionEntries(), $stub);

        return $stub;
    }

    private function optionEntries(): string
    {
        $options = $this->option('options');

        if (! is_string($options) || $options === '') {
            return '            // App\Options\ExampleOption::class,';
        }

        $entries = collect(explode(',', $options))
            ->map(fn (string $option): string => trim($option))
            ->filter(fn (string $option): bool => $option !== '')
            ->map(fn (string $option): string => '            '.$option.'::class,')
            ->implode(PHP_EOL);

        return $entries === ''
            ? '            // App\Options\ExampleOption::class,'
            : $entries;
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['options', null, InputOption::VALUE_OPTIONAL, 'Comma-separated option class-strings to pre-fill'],
        ];
    }
}
