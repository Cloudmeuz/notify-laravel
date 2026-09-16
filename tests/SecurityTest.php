<?php

declare(strict_types=1);

use CloudMe\Notify\Exceptions\ConfigurationException;
use CloudMe\Notify\NotifyClient;
use CloudMe\NotifyLaravel\Tests\TestKeys;
use Illuminate\Support\Facades\Artisan;

it('never prints the api_key or private key in notify:doctor output', function () {
    $secretApiKey = 'nk_test_super_secret_value_12345';
    $privateKeyPem = TestKeys::privateKeyPem();

    config([
        'notify.api_key' => $secretApiKey,
        'notify.private_key' => $privateKeyPem,
        'notify.private_key_path' => null,
    ]);

    Artisan::call('notify:doctor');
    $output = Artisan::output();

    expect($output)->not->toContain($secretApiKey)
        ->and($output)->not->toContain($privateKeyPem)
        ->and($output)->not->toContain('BEGIN')
        ->and($output)->not->toContain('PRIVATE KEY');
});

it('never embeds a secret value in a configuration exception message', function () {
    $secretApiKey = 'nk_test_super_secret_value_12345';

    config(['notify.api_key' => $secretApiKey, 'notify.token_store' => 'not-a-real-store']);
    $this->app->forgetInstance(NotifyClient::class);

    try {
        $this->app->make(NotifyClient::class);
        $this->fail('Expected a ConfigurationException.');
    } catch (ConfigurationException $e) {
        expect($e->getMessage())->not->toContain($secretApiKey);
    }
});
