<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Tests;

use CloudMe\NotifyLaravel\Facades\Notify as NotifyFacade;
use CloudMe\NotifyLaravel\NotifyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NotifyServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Notify' => NotifyFacade::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('notify.environment', 'sandbox');
        $app['config']->set('notify.client_id', 'test-client-id');
        $app['config']->set('notify.api_key', 'test-api-key');
        $app['config']->set('notify.private_key', TestKeys::privateKeyPem());
        $app['config']->set('notify.token_store', 'array');
        $app['config']->set('cache.default', 'array');
    }
}
