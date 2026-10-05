<?php

declare(strict_types=1);

/*
 * The second writer of the concurrent-first-write race (see UniqueRowsTest). Boots the
 * package on a bare Testbench app against the suite's database — the TESTING_DB_*
 * environment is inherited from the test process — and writes the option inside a batch
 * transaction behind the barrier. Prints its outcome as JSON.
 *
 * Usage: php write.php <barrier-dir> <value>
 */

use Orchestra\Testbench\Foundation\Application;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsServiceProvider;
use RoundlyConsulting\Options\Tests\Fixtures\Race\Barrier;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Testing\Database\DriverMatrix;

require __DIR__.'/../../../vendor/autoload.php';

[, $directory, $value] = $argv;

$app = Application::create(options: ['extra' => ['dont-discover' => ['*']]]);

DriverMatrix::configure($app);
$app->register(OptionsServiceProvider::class);

(new Barrier($directory, 'child', 'parent'))->arm();

echo json_encode(Barrier::attempt(static fn () => Options::setMany([ThemeOption::class => $value])));
