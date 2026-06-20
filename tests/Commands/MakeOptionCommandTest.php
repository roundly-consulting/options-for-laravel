<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->target = app_path('Options');

    File::deleteDirectory($this->target);
});

afterEach(function (): void {
    File::deleteDirectory($this->target);
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
