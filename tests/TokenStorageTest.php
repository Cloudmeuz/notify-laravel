<?php

declare(strict_types=1);

use CloudMe\NotifyLaravel\Cache\LaravelTokenStorage;
use CloudMe\NotifyLaravel\Support\ClientConfig;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;

function sampleTokens(string $access = 'access-1', string $refresh = 'refresh-1'): array
{
    return [
        'access_token' => $access,
        'access_token_expires_at' => time() + 900,
        'refresh_token' => $refresh,
        'refresh_token_expires_at' => time() + (14 * 86400),
    ];
}

it('returns null when nothing has been stored yet', function () {
    $storage = new LaravelTokenStorage(new Repository(new ArrayStore), 'notify:sandbox:cmp_1:tokens');

    expect($storage->get())->toBeNull();
});

it('stores and retrieves the token pair', function () {
    $storage = new LaravelTokenStorage(new Repository(new ArrayStore), 'notify:sandbox:cmp_1:tokens');

    $storage->put(sampleTokens());

    expect($storage->get())->toBe(sampleTokens());
});

it('clears the stored token pair', function () {
    $storage = new LaravelTokenStorage(new Repository(new ArrayStore), 'notify:sandbox:cmp_1:tokens');

    $storage->put(sampleTokens());
    $storage->clear();

    expect($storage->get())->toBeNull();
});

it('works even when the underlying store does not support atomic locks', function () {
    $store = new class implements Store
    {
        private array $items = [];

        public function get($key)
        {
            return $this->items[$key] ?? null;
        }

        public function many(array $keys)
        {
            return array_map(fn ($key) => $this->get($key), $keys);
        }

        public function put($key, $value, $seconds)
        {
            $this->items[$key] = $value;

            return true;
        }

        public function putMany(array $values, $seconds)
        {
            foreach ($values as $key => $value) {
                $this->put($key, $value, $seconds);
            }

            return true;
        }

        public function increment($key, $value = 1)
        {
            return $this->items[$key] = ($this->items[$key] ?? 0) + $value;
        }

        public function decrement($key, $value = 1)
        {
            return $this->increment($key, -$value);
        }

        public function forever($key, $value)
        {
            return $this->put($key, $value, 0);
        }

        public function touch($key, $seconds)
        {
            return true;
        }

        public function forget($key)
        {
            unset($this->items[$key]);

            return true;
        }

        public function flush()
        {
            $this->items = [];

            return true;
        }

        public function getPrefix()
        {
            return '';
        }
    };

    $storage = new LaravelTokenStorage(new Repository($store), 'notify:sandbox:cmp_1:tokens');

    $storage->put(sampleTokens());

    expect($storage->get())->toBe(sampleTokens());
});

it('isolates sandbox and production token cache keys', function () {
    $cache = new Repository(new ArrayStore);

    $sandboxKey = ClientConfig::tokenCacheKey(['cache' => ['prefix' => 'notify'], 'environment' => 'sandbox', 'client_id' => 'cmp_1']);
    $productionKey = ClientConfig::tokenCacheKey(['cache' => ['prefix' => 'notify'], 'environment' => 'production', 'client_id' => 'cmp_1']);

    expect($sandboxKey)->not->toBe($productionKey);

    $sandboxStorage = new LaravelTokenStorage($cache, $sandboxKey);
    $productionStorage = new LaravelTokenStorage($cache, $productionKey);

    $sandboxStorage->put(sampleTokens('sandbox-access'));
    $productionStorage->put(sampleTokens('production-access'));

    expect($sandboxStorage->get()['access_token'])->toBe('sandbox-access')
        ->and($productionStorage->get()['access_token'])->toBe('production-access');
});

it('isolates token caches between different client_ids', function () {
    $cache = new Repository(new ArrayStore);

    $clientAKey = ClientConfig::tokenCacheKey(['cache' => ['prefix' => 'notify'], 'environment' => 'sandbox', 'client_id' => 'cmp_a']);
    $clientBKey = ClientConfig::tokenCacheKey(['cache' => ['prefix' => 'notify'], 'environment' => 'sandbox', 'client_id' => 'cmp_b']);

    expect($clientAKey)->not->toBe($clientBKey);

    $storageA = new LaravelTokenStorage($cache, $clientAKey);
    $storageB = new LaravelTokenStorage($cache, $clientBKey);

    $storageA->put(sampleTokens('client-a-access'));

    expect($storageA->get()['access_token'])->toBe('client-a-access')
        ->and($storageB->get())->toBeNull();
});

it('never puts the raw api_key into the cache key', function () {
    $key = ClientConfig::tokenCacheKey([
        'cache' => ['prefix' => 'notify'],
        'environment' => 'sandbox',
        'client_id' => 'cmp_1',
        'api_key' => 'nk_test_super_secret_value',
    ]);

    expect($key)->not->toContain('nk_test_super_secret_value');
});
