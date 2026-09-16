<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Tests;

/**
 * Generates (once per test process) a throwaway RSA key pair for tests -
 * never used against the real Notify API, only against a Guzzle
 * MockHandler or not at all (most tests here never sign a request).
 */
final class TestKeys
{
    private static ?string $privateKeyPem = null;

    public static function privateKeyPem(): string
    {
        if (self::$privateKeyPem !== null) {
            return self::$privateKeyPem;
        }

        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        if ($configPath = self::opensslConfigPath()) {
            $options['config'] = $configPath;
        }

        $resource = openssl_pkey_new($options);
        openssl_pkey_export($resource, $pem, null, $options);

        return self::$privateKeyPem = $pem;
    }

    /**
     * Windows PHP builds without a bundled openssl.cnf can't generate a key
     * without one being pointed to explicitly - mirrors the same workaround
     * used in cloudme/notify-php's test suite.
     */
    private static function opensslConfigPath(): ?string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return null;
        }

        foreach ([getenv('OPENSSL_CONF'), getenv('USERPROFILE').'\\.config\\herd\\openssl.cnf'] as $candidate) {
            if ($candidate !== false && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
