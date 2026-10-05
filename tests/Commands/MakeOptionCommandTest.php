<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
 * The generator writes into app/Options: a throwaway app/ per test, never the shared testbench
 * skeleton every parallel process boots from. The app path is read when the command runs, so
 * pointing it here is enough. The namespace is resolved first — Laravel derives it by
 * matching app/ against the skeleton's composer.json, which a sandbox would not match.
 */
beforeEach(function (): void {
    $this->app->getNamespace();
    $this->app->useAppPath($this->sandbox = sys_get_temp_dir().'/options-make-option-'.bin2hex(random_bytes(6)));

    $this->target = app_path('Options');
});

afterEach(fn () => File::deleteDirectory($this->sandbox));

it('generates into the sandbox, never the shared skeleton', function (): void {
    expect($this->target)->toContain('options-make-option-');
});

it('generates an option class with key and cast', function (): void {
    $this->artisan('make:option', ['name' => 'ThemeOption', '--cast' => 'string'])
        ->assertSuccessful();

    $path = $this->target.'/ThemeOption.php';

    expect(File::exists($path))->toBeTrue();

    $contents = File::get($path);

    expect($contents)
        ->toContain('class ThemeOption extends BaseOption')
        ->toContain("return 'ThemeOption';")
        ->toContain("return 'string';")
        ->toContain('return false;');
});

it('honours a custom key', function (): void {
    $this->artisan('make:option', ['name' => 'ThemeOption', '--key' => 'theme'])
        ->assertSuccessful();

    expect(File::get($this->target.'/ThemeOption.php'))
        ->toContain("return 'theme';");
});

it('generates an encrypted option', function (): void {
    $this->artisan('make:option', ['name' => 'SecretOption', '--encrypted' => true])
        ->assertSuccessful();

    expect(File::get($this->target.'/SecretOption.php'))
        ->toContain('return true;');
});

it('generates an enum option', function (): void {
    $this->artisan('make:option', [
        'name' => 'StatusOption',
        '--enum' => 'App\\Enums\\Status',
    ])->assertSuccessful();

    $contents = File::get($this->target.'/StatusOption.php');

    expect($contents)
        ->toContain('use App\\Enums\\Status;')
        ->toContain('EnumCast::class')
        ->toContain('Status::class');
});

it('escapes quotes in the key and cast it writes into the class', function (): void {
    // Regression (2026-10-05 chat review, C-14): `--key="user's_theme"` was substituted raw
    // into a single-quoted string, so the generated class was a parse error.
    $class = 'QuotedOption'.bin2hex(random_bytes(4));

    $this->artisan('make:option', ['name' => $class, '--key' => "user's_theme\\", '--cast' => "it's"])
        ->assertSuccessful();

    $path = $this->target.'/'.$class.'.php';
    $lint = new Process([PHP_BINARY, '-l', $path]);
    $lint->run();

    expect($lint->getExitCode())->toBe(0, $lint->getOutput().$lint->getErrorOutput());

    require $path;

    $option = new ('App\\Options\\'.$class);

    expect($option->key())->toBe("user's_theme\\")
        ->and($option->castAs())->toBe("it's");
});
