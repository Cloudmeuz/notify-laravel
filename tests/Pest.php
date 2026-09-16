<?php

declare(strict_types=1);

use CloudMe\Notify\NotifyClient;
use CloudMe\NotifyLaravel\Tests\TestCase;
use CloudMe\NotifyLaravel\Tests\TestKeys;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

uses(TestCase::class)->in(__DIR__);

function jsonResponse(int $status, array $body = []): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
}

/**
 * Builds a real NotifyClient wired to a Guzzle MockHandler instead of the
 * network - no real HTTP call ever leaves the test process. Used to swap
 * out the container-bound NotifyClient singleton in a test via
 * `$this->app->instance(NotifyClient::class, mockedNotifyClient([...]))`.
 *
 * @param  array<int, Response>  $responses  served in order, one per HTTP call
 * @param  array<int, array<string, mixed>>  $history  passed by reference, populated as calls happen
 */
function mockedNotifyClient(array $responses, array &$history = []): NotifyClient
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return new NotifyClient(
        clientId: 'test-client-id',
        apiKey: 'test-api-key',
        privateKey: TestKeys::privateKeyPem(),
        baseUrl: 'https://notify.test/api/v1',
        options: ['handler' => $stack],
    );
}

/**
 * @return array{access_token: string, expires_in: int, refresh_token: string}
 */
function tokenResponseBody(string $accessToken = 'access-token-1', int $expiresIn = 900, string $refreshToken = 'refresh-token-1'): array
{
    return ['token_type' => 'Bearer', 'access_token' => $accessToken, 'expires_in' => $expiresIn, 'refresh_token' => $refreshToken];
}
