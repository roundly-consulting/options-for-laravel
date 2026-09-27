# Changelog

All notable changes to `options-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
- JSON import / export and the `make:option`, `make:option-group`, `options:list`, `options:get`,
  `options:set`, `options:export`, `options:import` and `options:clear-cache` commands.
- `Options::fake()` with assertions for testing host applications.
