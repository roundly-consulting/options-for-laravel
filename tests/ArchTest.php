<?php

declare(strict_types=1);

use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Exceptions\OptionException;
use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Options shipped one generic arch rule (`dd`/`dump`/`ray` not used), which
 * `noDebuggingLeftovers` below covers with a wider ban. Everything else here is a new
 * guard.
 */
ArchPresets::strictTypes('RoundlyConsulting\Options');

/**
 * Options is a package built to be extended, so the exemption list is long *and*
 * deliberate — each entry is a documented extension point, not an oversight:
 *
 *  - Option — `options.model` invites a host to subclass it (pinned by the preset below).
 *  - BaseOption / OptionGroup — the whole public API. A host defines its settings by
 *    extending these; `make:option` scaffolds exactly that.
 *  - OptionsManager — the package ships `OptionsFake extends OptionsManager` as its
 *    own test double (`toBeFakeable()` pins the subtype), so closing it would break the fake.
 *  - OptionException — the base every options error extends, so a host can catch them
 *    uniformly.
 *
 * The list goes through the `$ignoring` PARAMETER rather than Pest's fluent `->ignoring()`,
 * which is neither rot-checked nor shadow-recovered. Both matter here, and the second one
 * is not hypothetical: Pest matches exemptions by string PREFIX (pest-plugin-arch
 * Blueprint.php:103), and `Option::class` is a prefix of half this package. It silently
 * also silences `OptionContext` and `OptionsServiceProvider` — neither of which anyone
 * exempted — and it reaches `OptionsManager` too, which is named above anyway. Through the
 * parameter, `finalByDefault` re-checks the unnamed two by reflection; both are final, so
 * this is green today and stays a guard against either being opened later.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Options', [
    Option::class,
    BaseOption::class,
    OptionGroup::class,
    OptionsManager::class,
    OptionException::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. The preset
 * also pins that `options.model` really defaults to the packaged model, so the seam cannot
 * rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Option::class => 'options.model',
]);

/**
 * Options *encrypts* — `EncryptedCast` is the single most security-relevant thing here, and
 * it must keep going through Laravel's Crypt facade rather than reaching for openssl_* or
 * hashing a value locally. This preset is the standing guard on exactly that.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Options');

/*
 * `ArchPresets::modelsResolveThroughSeam()` is REJECTED here, with cause — the second
 * eligible package to hit this, after jwt.
 *
 * Options is eligible on paper (a real Eloquent model behind `options.model`, resolved
 * through a Support seam) and its seam half would pass: nothing outside
 * Support/OptionModel.php reads the swap literal, and every call site goes through
 * `OptionModel::class()`.
 *
 * It fails on the other half. The preset bans the *tokens* `new static` / `static::query()`
 * / `self::query()` in **every file under src**, but options' public API is built on
 * `new static`:
 *
 *     abstract class BaseOption {
 *         public static function for(?Model $owner): static { return new static($owner); }
 *         public static function make(mixed ...$arguments): static { return new static(...$arguments); }
 *     }
 *
 * That is correct and load-bearing. BaseOption is not an Eloquent model and has no
 * relationship to `options.model`; it is the abstract class a host extends to define a
 * setting, and `new static` is the only way `ThemeOption::for($user)` can return a
 * ThemeOption. Rewriting it to satisfy the ban would break every option a host has written.
 *
 * The preset's own docblock scopes the rule to late static binding "inside a model or
 * helper" — the implementation does not, and it registers as an `it()` case with no
 * `->ignoring()` escape, so there is no way to adopt the seam half alone. jwt rejected it
 * for the same reason (its ban would have forbidden the `new static` that *fixed* bug #5).
 * Per this plan's own ">1 package = testing-package defect" rule, that makes this a defect
 * in the testing package, not a problem with options — reported rather than worked around
 * here, and deliberately NOT silenced by narrowing $srcDir to dodge BaseOption.php.
 */

/**
 * The morph-key seam, guarded. The options table reaches its polymorphic owner column through
 * `morphKey($name, KeyType::fromConfig('options.key_type'))`, never a raw `$table->morphs()`,
 * so a uuid/ulid host keys option ownership coherently — a hardcoded bigint id breaks those
 * hosts on Postgres, and SQLite type affinity hides it. This pin reds if a future migration
 * reintroduces a raw morph and bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: options' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/** The `HasOptions` trait reaches behaviour through the manager, never an action. */
ArchPresets::modelsGoThroughTheFacade('RoundlyConsulting\Options');
