<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Cache;

use CloudMe\Notify\Auth\TokenStorage;
use CloudMe\NotifyLaravel\Support\ClientConfig;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;

/**
 * Persists the Notify access/refresh token pair in a Laravel cache store, so
 * a web request or a queued job doesn't re-authenticate on every single
 * call - see cloudme/notify-php's TokenManager for how the pair is used.
 *
 * The cache key is namespaced by environment and client_id (see
 * {@see ClientConfig::tokenCacheKey()}), so
 * sandbox/production tokens and multiple API clients never share a slot.
 *
 * Concurrency note: writes are wrapped in an atomic cache lock when the
 * underlying store supports one (Redis, Memcached, DynamoDB), which keeps a
 * single write from being corrupted by an interleaved one. It does not, and
 * cannot, prevent two concurrent requests from both deciding to refresh with
 * the same still-cached refresh token - that decision is made inside
 * TokenManager itself, before either write reaches here, so it isn't
 * something a TokenStorage implementation can arbitrate. If that happens,
 * Notify's server-side reuse detection revokes the pair, and the "losing"
 * caller sees an AuthenticationException from the refresh call - which
 * TokenManager already catches and recovers from by re-authenticating from
 * scratch (a fresh signed /oauth/token call), so the practical effect is one
 * extra authentication round-trip rather than a failed send.
 */
final class LaravelTokenStorage implements TokenStorage
{
    private const LOCK_SECONDS = 5;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $key,
    ) {}

    public function get(): ?array
    {
        $tokens = $this->cache->get($this->key);

        return is_array($tokens) ? $tokens : null;
    }

    public function put(array $tokens): void
    {
        $ttl = max(1, $tokens['refresh_token_expires_at'] - time());

        $store = $this->cache->getStore();

        if ($store instanceof LockProvider) {
            $store->lock($this->key.':lock', self::LOCK_SECONDS)->block(
                self::LOCK_SECONDS,
                fn () => $this->cache->put($this->key, $tokens, $ttl),
            );

            return;
        }

        $this->cache->put($this->key, $tokens, $ttl);
    }

    public function clear(): void
    {
        $this->cache->forget($this->key);
    }
}
