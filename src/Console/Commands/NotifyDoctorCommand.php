<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Console\Commands;

use CloudMe\Notify\Exceptions\ConfigurationException;
use CloudMe\NotifyLaravel\Support\ClientConfig;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnoses the Notify configuration without ever sending a real message or
 * printing a secret - safe to run in production.
 */
final class NotifyDoctorCommand extends Command
{
    protected $signature = 'notify:doctor';

    protected $description = 'Diagnose the Notify configuration (no messages are sent, no secrets are shown)';

    public function handle(ConfigRepository $config, CacheManager $cacheManager): int
    {
        $notifyConfig = (array) $config->get('notify', []);
        $healthy = true;

        $this->components->info('Notify configuration diagnostics');

        try {
            $environment = ClientConfig::environment($notifyConfig);
            $this->components->twoColumnDetail('Environment', $environment);
        } catch (ConfigurationException $e) {
            $this->components->twoColumnDetail('Environment', 'invalid');
            $this->components->error($e->getMessage());
            $healthy = false;
        }

        try {
            $this->components->twoColumnDetail('Base URL', ClientConfig::baseUrl($notifyConfig));
        } catch (ConfigurationException $e) {
            $this->components->twoColumnDetail('Base URL', 'unresolved');
            $this->components->error($e->getMessage());
            $healthy = false;
        }

        try {
            ClientConfig::clientId($notifyConfig);
            $this->components->twoColumnDetail('Client ID', 'configured');
        } catch (ConfigurationException $e) {
            $this->components->twoColumnDetail('Client ID', 'missing');
            $this->components->error($e->getMessage());
            $healthy = false;
        }

        try {
            ClientConfig::apiKey($notifyConfig);
            $this->components->twoColumnDetail('API key', 'configured');
        } catch (ConfigurationException $e) {
            $this->components->twoColumnDetail('API key', 'missing');
            $this->components->error($e->getMessage());
            $healthy = false;
        }

        $healthy = $this->checkPrivateKey($notifyConfig) && $healthy;
        $healthy = $this->checkTokenStore($notifyConfig, $cacheManager) && $healthy;

        if ($healthy) {
            $this->components->info('All checks passed. No messages were sent.');

            return self::SUCCESS;
        }

        $this->components->warn('One or more checks failed. No messages were sent.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $notifyConfig
     */
    private function checkPrivateKey(array $notifyConfig): bool
    {
        $path = $notifyConfig['private_key_path'] ?? null;
        $inline = $notifyConfig['private_key'] ?? null;

        if (is_string($path) && trim($path) !== '') {
            $readable = is_file($path) && is_readable($path);
            $this->components->twoColumnDetail('Private key file', $readable ? 'readable' : 'NOT readable');

            if (! $readable) {
                $this->components->error("Configured NOTIFY_PRIVATE_KEY_PATH is not a readable file: {$path}");
            }

            return $readable;
        }

        if (is_string($inline) && trim($inline) !== '') {
            $looksLikePem = str_contains($inline, '-----BEGIN') && str_contains($inline, 'PRIVATE KEY-----');
            $this->components->twoColumnDetail('Private key (inline)', $looksLikePem ? 'present' : 'present, but does not look like a PEM key');

            return $looksLikePem;
        }

        $this->components->twoColumnDetail('Private key', 'missing');
        $this->components->error('No Notify private key configured - set NOTIFY_PRIVATE_KEY_PATH or NOTIFY_PRIVATE_KEY.');

        return false;
    }

    /**
     * @param  array<string, mixed>  $notifyConfig
     */
    private function checkTokenStore(array $notifyConfig, CacheManager $cacheManager): bool
    {
        $store = $notifyConfig['token_store'] ?? 'cache';

        if (! in_array($store, ['cache', 'array'], true)) {
            $this->components->twoColumnDetail('Token store', 'invalid');
            $this->components->error("Invalid notify.token_store \"{$store}\" - expected \"cache\" or \"array\".");

            return false;
        }

        if ($store === 'array') {
            $this->components->twoColumnDetail('Token store', 'array (in-memory only - re-authenticates every process)');

            return true;
        }

        $this->components->twoColumnDetail('Token store', 'cache');

        try {
            $cache = $cacheManager->store($notifyConfig['cache']['store'] ?? null);
            $probeKey = 'notify:doctor:probe:'.bin2hex(random_bytes(4));
            $cache->put($probeKey, true, 5);
            $reachable = $cache->pull($probeKey) === true;

            $this->components->twoColumnDetail('Cache store reachable', $reachable ? 'yes' : 'no');

            if (! $reachable) {
                $this->components->error('The configured cache store did not return the test value that was just written to it.');
            }

            return $reachable;
        } catch (Throwable $e) {
            $this->components->twoColumnDetail('Cache store reachable', 'no');
            $this->components->error('Cache store error: '.$e->getMessage());

            return false;
        }
    }
}
