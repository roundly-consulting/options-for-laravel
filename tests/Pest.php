<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Tests\Models\SwappedOptionTestCase;
use RoundlyConsulting\Options\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different base
// case, and a blanket bind would claim it first. ArchTest.php is listed because
// `swappableModelsAreNotFinal` reads the `options.model` config default and so needs the
// app booted — an arch file is not automatically test-cased.
uses(TestCase::class)->in(
    'ArchTest.php',
    'CollectionOptionTest.php',
    'HelpersTest.php',
    'OptionFactoryStatesTest.php',
    'OptionModelTest.php',
    'OptionsManagerTest.php',
    'RegistryTest.php',
    'ServiceProviderTest.php',
    'SimpleOptionTest.php',
    'Actions',
    'Authorization',
    'Blade',
    'Cache',
    'Casts',
    'Commands',
    'Config',
    'Events',
    'Feature',
    'Fluent',
    'Groups',
    'Observers',
    'Options',
    'Settings',
    'Support',
    'Testing',
);

// The model-swap proofs need `options.model` pointed at the host subclass BEFORE the
// providers boot, so they run on their own base case in their own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedOptionTestCase::class)->in('ModelSwap');
