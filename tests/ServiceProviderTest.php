<?php

declare(strict_types=1);

use CloudMe\Notify\NotifyClient;
use CloudMe\NotifyLaravel\NotifyServiceProvider;
use Illuminate\Support\Facades\Artisan;

it('registers the service provider', function () {
    expect($this->app->getProviders(NotifyServiceProvider::class))->not->toBeEmpty();
});

it('merges the package config', function () {
    expect(config('notify.urls.production'))->toBe('https://api.notify.cloudme.uz/api/v1')
        ->and(config('notify.urls.sandbox'))->toBe('https://sandbox.notify.cloudme.uz/api/v1')
        ->and(config('notify.token_store'))->toBe('array');
});

it('resolves NotifyClient out of the container', function () {
    expect($this->app->make(NotifyClient::class))->toBeInstanceOf(NotifyClient::class);
});

it('resolves NotifyClient as a singleton', function () {
    $first = $this->app->make(NotifyClient::class);
    $second = $this->app->make(NotifyClient::class);

    expect($first)->toBe($second);
});

it('resolves NotifyClient through the "notify" alias', function () {
    expect($this->app->make('notify'))->toBe($this->app->make(NotifyClient::class));
});

it('sends the configured locale as Accept-Language, and none by default', function (?string $locale, ?string $header) {
    config(['notify.locale' => $locale]);

    $client = $this->app->make(NotifyClient::class);
    $http = (fn () => $this->http)->call($client);
    $guzzle = (fn () => $this->client)->call($http);

    expect($guzzle->getConfig('headers')['Accept-Language'] ?? null)->toBe($header);
})->with([
    'configured' => ['ru', 'ru'],
    'empty' => ['', null],
    'missing' => [null, null],
]);

it('registers the notify:doctor command', function () {
    expect(Artisan::all())->toHaveKey('notify:doctor');
});
