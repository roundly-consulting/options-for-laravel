# Changelog

All notable changes to `options-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Fixed

- An `EnumCast` option refuses a value that is not one of its enum's cases (a misspelt string,
  another enum's case) with `InvalidOptionPayload`, instead of storing it and reading back `null`.
- `make:option` escapes a `--key` or `--cast` containing a quote or backslash, so the generated
  class parses.
- `options:set --json` with invalid JSON fails with a message instead of storing the raw string.
- `options:set` reports a value its `rules()` refuse, and `options:set` / `get` / `list` /
  `export` report an unknown `--owner-id`, as a failed command instead of a stack trace.
- `Options::fake()` throws `InvalidOptionClassName` from `has()` / `forget()` for an option that
  is not a `BaseOption`, as the real manager does.
- The README's example option adds `required` to its rules: Laravel skips `in:` for a blank
  string, so the documented option stored `''`.
- On MySQL, two concurrent first writes of an option inside a transaction (`setMany()`, a group
  `set()`, a host transaction) no longer fail with a duplicate-key error: the loser re-reads the
  winner's row with a locking read and updates it.
- Observers (`Options::observe()`) and the live config bridge (`config_overrides_live`) run on
  every write, also with `options.events.enabled` off or the option events faked — they were
  event listeners, while `Options::fake()` always fired observers.
- A write inside a transaction caches its value for other processes, fires `OptionSet` /
  `OptionForgotten`, calls observers and re-applies live config overrides only after the commit,
  and not at all when it rolls back. Other processes were served the rolled-back value for the
  whole cache TTL.
- A read that raced a concurrent write, forget or import no longer caches the old value over
  the new one: reads fill the persistent cache only if it is empty (`add()`), and a forget or
  import puts its result instead of just dropping the entry.
- After the cache generation key went missing (`cache:clear`, an eviction), concurrent requests
  no longer split onto different generations with writes landing where no request reads: the
  generation is minted with `add()` and read back, and writes go under the generation the store
  holds now.
- `remember()` stores only if nothing is stored by then, so two concurrent first callers both
  return the one stored value; one used to return a value that was then overwritten.
- The config bridge no longer applies stored values while `config:cache` / `optimize` builds the
  config cache, which baked them — decrypted, for encrypted options — into
  `bootstrap/cache/config.php`, where a `forget()` could never undo them.
- `import()` is all or nothing: a non-scalar `value` is refused up front with
  `InvalidOptionPayload` (it used to surface as a `QueryException` after earlier rows had
  committed, and `options:import` printed a stack trace), and the rows are written in one
  transaction. `Options::fake()` refuses the same input.
- With `options.authorization` enabled, `export()` / `exportJson()` leave out options the current
  user may not read (and unregistered keys, as `all()` does), and `import()` refuses rows the user
  may not write with `UnauthorizedOption`, importing nothing. The `options:export` /
  `options:import` commands bypass authorization like the other commands; the fake enforces the
  same rules.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Typed options as small `BaseOption` classes — key, default, cast, optional encryption at rest
  and validation `rules()` — stored globally or scoped to any Eloquent model.
- The `Options` facade with `get` / `set` / `has` / `forget` / `remember`, a fluent
  `Options::for($owner)->option(...)` API, bulk `many()` / `setMany()` / `all()`, and the
  `options()` / `setting()` helpers.
- A string-key registry, the `HasOptions` model trait, the `@option` Blade directive and an
  `EnumCast` for backed enums.
- Setting groups (`OptionGroup`) that resolve, describe and bulk-write related options for
  settings screens.
- Opt-in per-option authorization (`authorizeRead()` / `authorizeWrite()`, optional Gate
  abilities), with `actingAs()` and `withoutAuthorization()` escapes.
- A config bridge that lets stored options override `config()` values
  (`options.config_overrides`).
- `OptionSet`, `OptionForgotten` and `OptionResolved` events, plus per-option observers via
  `Options::observe()`.
- In-request memoisation and optional persistent caching with automatic invalidation.
- JSON import / export on the facade — `Options::export()`, `exportJson()`, `import()` and
  `Options::for($owner)->export()` — and the `make:option`, `make:option-group`, `options:list`,
  `options:get`, `options:set`, `options:export`, `options:import` and `options:clear-cache`
  commands.
- `Options::fake()` — an in-memory store that sees every write (facade, handles, option instances,
  the `HasOptions` trait, imports) and never touches the database, with `assertSet()`,
  `assertForgotten()`, `assertImported()` and their `assertNothing*()` opposites.
