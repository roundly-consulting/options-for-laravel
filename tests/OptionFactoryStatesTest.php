<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Tests\Models\User;

it('builds a global option', function (): void {
    $option = Option::factory()->global()->create();

    expect($option->owner_id)->toBeNull()
        ->and($option->owner_type)->toBeNull();
});

it('builds an option for an owner', function (): void {
    $user = User::create();

    $option = Option::factory()->forOwner($user)->create();

    expect($option->owner_id)->toBe($user->getKey())
        ->and($option->owner_type)->toBe($user->getMorphClass());
});

it('builds an option with a value', function (): void {
    $option = Option::factory()->value('custom')->create();

    expect($option->value)->toBe('custom');
});

it('builds an option with meta', function (): void {
    $option = Option::factory()->withMeta(['source' => 'seed'])->create();

    expect($option->meta->toArray())->toBe(['source' => 'seed']);
});
