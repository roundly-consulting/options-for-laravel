<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/options-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel">
    <img src="art/hero.png" alt="Options for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/options-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/options-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/options-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/options-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/options-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/options-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Options for Laravel

Global or per-model options, settings and preferences for Laravel. Each option is a small class
with its key, default value, cast and validation rules; values can belong to the whole app or to
any Eloquent model (a user, a team, a tenant) and are cached, typed and evented.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/options-for-laravel
php artisan vendor:publish --tag="options-migrations"
php artisan migrate
```

If your owner models have UUID/ULID keys, set `OPTIONS_KEY_TYPE` **before** migrating.

## Usage

Describe an option once (`php artisan make:option ThemeOption --key=theme` scaffolds it):

```php
use RoundlyConsulting\Options\BaseOption;

final class ThemeOption extends BaseOption
{
    public function key(): string
    {
        return 'theme';
    }

    public function default(): mixed
    {
        return 'light';
    }

    public function rules(): array|string
    {
        return ['in:light,dark'];
    }
}
```

Then read and write it — globally or for any model:

```php
use RoundlyConsulting\Options\Facades\Options;

Options::get(ThemeOption::class);                 // 'light' — the default until a value is stored
Options::set(ThemeOption::class, 'dark');         // validated against rules(), cached, fires OptionSet

Options::set(ThemeOption::class, 'light', $user); // scoped to one model
Options::for($user)->get(ThemeOption::class);     // 'light'
Options::forget(ThemeOption::class, $user);       // back to the default
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/options-for-laravel](https://roundly-consulting.com/open-source/docs/options-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
