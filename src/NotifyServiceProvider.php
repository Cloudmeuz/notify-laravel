<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel;

use CloudMe\Notify\Auth\ArrayTokenStorage;
use CloudMe\Notify\Auth\TokenStorage;
use CloudMe\Notify\Exceptions\ConfigurationException;
use CloudMe\Notify\NotifyClient;
use CloudMe\Notify\Webhooks\WebhookVerifier;
use CloudMe\NotifyLaravel\Cache\LaravelTokenStorage;
use CloudMe\NotifyLaravel\Console\Commands\NotifyDoctorCommand;
use CloudMe\NotifyLaravel\Http\Middleware\VerifyNotifyWebhook;
use CloudMe\NotifyLaravel\Support\ClientConfig;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class NotifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/notify.php', 'notify');

        $this->app->singleton(NotifyClient::class, function (Application $app): NotifyClient {
            return $this->buildClient($app);
        });

        $this->app->alias(NotifyClient::class, 'notify');

        $this->app->singleton(WebhookVerifier::class, function (Application $app): WebhookVerifier {
            $webhook = (array) $app->make('config')->get('notify.webhook', []);

            return new WebhookVerifier(
                (string) ($webhook['secret'] ?? ''),
                (int) ($webhook['tolerance'] ?? WebhookVerifier::DEFAULT_TOLERANCE_SECONDS),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make('router')->aliasMiddleware('notify.webhook', VerifyNotifyWebhook::class);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/notify.php' => $this->app->configPath('notify.php'),
        ], 'notify-config');

        $this->commands([
            NotifyDoctorCommand::class,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [NotifyClient::class, 'notify', WebhookVerifier::class];
    }

    private function buildClient(Application $app): NotifyClient
    {
        /** @var array<string, mixed> $config */
        $config = (array) $app->make('config')->get('notify', []);

        $http = (array) ($config['http'] ?? []);

        return new NotifyClient(
            clientId: ClientConfig::clientId($config),
            apiKey: ClientConfig::apiKey($config),
            privateKey: ClientConfig::privateKey($config),
            baseUrl: ClientConfig::baseUrl($config),
            tokenStorage: $this->buildTokenStorage($app, $config),
            options: [
                'timeout' => (float) ($http['timeout'] ?? 10),
                'connect_timeout' => (float) ($http['connect_timeout'] ?? 5),
                'max_retries' => (int) ($http['max_retries'] ?? 3),
            ],
            locale: isset($config['locale']) && $config['locale'] !== '' ? (string) $config['locale'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildTokenStorage(Application $app, array $config): TokenStorage
    {
        $store = $config['token_store'] ?? 'cache';

        return match ($store) {
            'array' => new ArrayTokenStorage,
            'cache' => new LaravelTokenStorage(
                $app->make(CacheManager::class)->store($config['cache']['store'] ?? null),
                ClientConfig::tokenCacheKey($config),
            ),
            default => throw new ConfigurationException(sprintf(
                'Invalid notify.token_store "%s" - expected "cache" or "array".',
                is_scalar($store) ? (string) $store : gettype($store),
            )),
        };
    }
}
