<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Tests\Models\User;

it('builds an option via its factory', function (): void {
    $option = Option::factory()->create(['key' => 'made', 'value' => 'by-factory']);

    expect($option)
        ->toBeInstanceOf(Option::class)
        ->key->toBe('made')
        ->value->toBe('by-factory');
});

it('soft deletes options', function (): void {
    $option = Option::factory()->create();

    $option->delete();

    expect($option->trashed())->toBeTrue();
    expect(Option::query()->count())->toBe(0);
    expect(Option::withTrashed()->count())->toBe(1);
});

it('scopes to a specific owner', function (): void {
    $owner = User::create();

    Option::create([
        'key' => 'k',
        'value' => 'owned',
        'owner_id' => $owner->id,
        'owner_type' => $owner->getMorphClass(),
    ]);

    Option::create(['key' => 'k', 'value' => 'global']);

    expect(Option::query()->forOwner($owner)->first()->value)->toBe('owned');
    expect(Option::query()->forOwner(null)->first()->value)->toBe('global');
});

it('resolves the owner morph relation', function (): void {
    $owner = User::create();

    $option = Option::create([
        'key' => 'k',
        'value' => 'v',
        'owner_id' => $owner->id,
        'owner_type' => $owner->getMorphClass(),
    ]);

    expect($option->owner)->toBeInstanceOf(User::class);
});
