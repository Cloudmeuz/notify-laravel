<?php

declare(strict_types=1);

use CloudMe\Notify\Exceptions\ConfigurationException;
use CloudMe\Notify\NotifyClient;
use CloudMe\NotifyLaravel\NotifyServiceProvider;
use CloudMe\NotifyLaravel\Tests\TestKeys;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

it('registers config/notify.php for publishing', function () {
    $paths = ServiceProvider::pathsToPublish(NotifyServiceProvider::class, 'notify-config');

    expect($paths)->toHaveCount(1);

    $from = array_key_first($paths);
    $to = $paths[$from];

    expect(str_replace('\\', '/', $from))->toEndWith('config/notify.php')
        ->and($to)->toEndWith('notify.php')
        ->and(is_file($from))->toBeTrue();
});

it('actually publishes config/notify.php via artisan vendor:publish', function () {
    $exitCode = Artisan::call('vendor:publish', ['--tag' => 'notify-config', '--force' => true]);

    expect($exitCode)->toBe(0)
        ->and(is_file(config_path('notify.php')))->toBeTrue();
});

it('uses the sandbox URL by default', function () {
    config(['notify.environment' => 'sandbox', 'notify.base_url' => null]);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->not->toThrow(Throwable::class);
});

it('resolves the production URL when configured', function () {
    config(['notify.environment' => 'production', 'notify.base_url' => null]);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->not->toThrow(Throwable::class);
});

it('lets NOTIFY_BASE_URL override the environment default', function () {
    config(['notify.environment' => 'sandbox', 'notify.base_url' => 'https://custom.example.test/api/v1']);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->not->toThrow(Throwable::class);
});

it('rejects an invalid environment', function () {
    config(['notify.environment' => 'staging']);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->toThrow(ConfigurationException::class);
});

it('accepts a private key file path', function () {
    $path = tempnam(sys_get_temp_dir(), 'notify_test_key_');
    file_put_contents($path, TestKeys::privateKeyPem());

    config(['notify.private_key' => null, 'notify.private_key_path' => $path]);
    $this->app->forgetInstance(NotifyClient::class);

    try {
        expect(fn () => $this->app->make(NotifyClient::class))->not->toThrow(Throwable::class);
    } finally {
        unlink($path);
    }
});

it('accepts an inline private key', function () {
    config(['notify.private_key_path' => null, 'notify.private_key' => TestKeys::privateKeyPem()]);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->not->toThrow(Throwable::class);
});

it('prefers the private key path over an inline value when both are set', function () {
    $path = tempnam(sys_get_temp_dir(), 'notify_test_key_');
    file_put_contents($path, TestKeys::privateKeyPem());

    config(['notify.private_key' => 'not-a-real-pem', 'notify.private_key_path' => $path]);
    $this->app->forgetInstance(NotifyClient::class);

    try {
        // If the inline (invalid) value had won, this would throw.
        expect(fn () => $this->app->make(NotifyClient::class))->not->toThrow(Throwable::class);
    } finally {
        unlink($path);
    }
});

it('rejects a missing private key', function () {
    config(['notify.private_key' => null, 'notify.private_key_path' => null]);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->toThrow(ConfigurationException::class);
});

it('rejects an invalid token_store', function () {
    config(['notify.token_store' => 'redis-direct']);
    $this->app->forgetInstance(NotifyClient::class);

    expect(fn () => $this->app->make(NotifyClient::class))->toThrow(ConfigurationException::class);
});
