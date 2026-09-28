<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

final class FingerprintTeam extends Model
{
    protected $table = 'fingerprint_teams';

    protected $guarded = [];

    public $timestamps = false;
}

afterEach(fn () => Relation::morphMap([], merge: false));

it('keeps owner type and id apart in the cache fingerprint', function (): void {
    // `team1` + `3` and `team` + `13` concatenate to the same string.
    Relation::morphMap(['team1' => User::class, 'team' => FingerprintTeam::class]);

    $user = (new User)->forceFill(['id' => 3]);
    $team = (new FingerprintTeam)->forceFill(['id' => 13]);

    Options::set(ThemeOption::class, 'user-3-private', $user);

    expect(OptionStore::fingerprint('theme', 'team1', 3))->not->toBe(OptionStore::fingerprint('theme', 'team', 13))
        ->and(Options::get(ThemeOption::class, $team))->toBe('light');

    app(Cache::class)->flush();

    // The persistent cache must not hand the user's value to the team either.
    expect(Options::get(ThemeOption::class, $team))->toBe('light')
        ->and(Options::get(ThemeOption::class, $user))->toBe('user-3-private');
});

it('treats an int and a numeric-string owner id as one scope', function (): void {
    expect(OptionStore::fingerprint('theme', 'user', 5))->toBe(OptionStore::fingerprint('theme', 'user', '5'))
        ->and(OptionStore::fingerprint('theme'))->toBe('options:theme:global');
});
