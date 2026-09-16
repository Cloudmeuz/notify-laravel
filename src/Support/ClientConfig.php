<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Support;

use CloudMe\Notify\Exceptions\ConfigurationException;

/**
 * Pure helpers for turning the "notify" config array into the values
 * NotifyClient's constructor needs - shared by NotifyServiceProvider and
 * the notify:doctor command so both apply the exact same priority rules.
 */
final class ClientConfig
{
    private function __construct() {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function environment(array $config): string
    {
        $environment = $config['environment'] ?? null;

        if (! in_array($environment, ['production', 'sandbox'], true)) {
            throw new ConfigurationException(sprintf(
                'Invalid notify.environment "%s" - expected "production" or "sandbox".',
                is_scalar($environment) ? (string) $environment : gettype($environment),
            ));
        }

        return $environment;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function baseUrl(array $config): string
    {
        $override = $config['base_url'] ?? null;

        if (is_string($override) && trim($override) !== '') {
            return $override;
        }

        $environment = self::environment($config);
        $url = $config['urls'][$environment] ?? null;

        if (! is_string($url) || trim($url) === '') {
            throw new ConfigurationException("No base URL configured for the \"{$environment}\" environment.");
        }

        return $url;
    }

    /**
     * Returns either a filesystem path or inline PEM contents - both are
     * accepted as-is by cloudme/notify-php's SignatureSigner.
     *
     * @param  array<string, mixed>  $config
     */
    public static function privateKey(array $config): string
    {
        $path = $config['private_key_path'] ?? null;

        if (is_string($path) && trim($path) !== '') {
            return $path;
        }

        $inline = $config['private_key'] ?? null;

        if (is_string($inline) && trim($inline) !== '') {
            return $inline;
        }

        throw new ConfigurationException(
            'No Notify private key configured - set NOTIFY_PRIVATE_KEY_PATH or NOTIFY_PRIVATE_KEY.'
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function clientId(array $config): string
    {
        $clientId = $config['client_id'] ?? null;

        if (! is_string($clientId) || trim($clientId) === '') {
            throw new ConfigurationException('No Notify client_id configured - set NOTIFY_CLIENT_ID.');
        }

        return $clientId;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function apiKey(array $config): string
    {
        $apiKey = $config['api_key'] ?? null;

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new ConfigurationException('No Notify api_key configured - set NOTIFY_API_KEY.');
        }

        return $apiKey;
    }

    /**
     * The cache key the token pair is stored under - namespaced by
     * environment and client_id so sandbox/production and multiple API
     * clients never collide or leak into each other's cached tokens.
     *
     * @param  array<string, mixed>  $config
     */
    public static function tokenCacheKey(array $config): string
    {
        $prefix = $config['cache']['prefix'] ?? 'notify';
        $environment = self::environment($config);
        $clientId = self::clientId($config);

        return "{$prefix}:{$environment}:{$clientId}:tokens";
    }
}
