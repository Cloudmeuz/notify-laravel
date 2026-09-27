<?php

declare(strict_types=1);

use CloudMe\Notify\Exceptions\ConfigurationException;
use CloudMe\Notify\Webhooks\WebhookVerifier;
use CloudMe\NotifyLaravel\Http\Middleware\VerifyNotifyWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['notify.webhook.secret' => 'whsec_laravel_test']);

    Route::post('/webhooks/notify', fn (Request $request) => response()->json([
        'id' => VerifyNotifyWebhook::event($request)->id,
        'event' => VerifyNotifyWebhook::event($request)->event,
        'message_id' => VerifyNotifyWebhook::event($request)->messageId,
    ]))->middleware('notify.webhook');
});

/**
 * Posts a raw JSON body with the headers the Notify server sends.
 */
function postNotifyWebhook(string $body, ?string $signature = null, ?int $timestamp = null): TestResponse
{
    $timestamp ??= time();
    $signature ??= hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_laravel_test');

    return test()->call('POST', '/webhooks/notify', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_WEBHOOK_ID' => 'delivery-uuid-1',
        'HTTP_X_WEBHOOK_EVENT' => 'message.delivered',
        'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => $signature,
    ], content: $body);
}

it('passes a correctly signed webhook through with the parsed event', function () {
    postNotifyWebhook('{"event":"message.delivered","message_id":"msg-1","status":"delivered"}')
        ->assertOk()
        ->assertExactJson(['id' => 'delivery-uuid-1', 'event' => 'message.delivered', 'message_id' => 'msg-1']);
});

it('rejects a bad signature, a tampered body or a stale timestamp', function () {
    $body = '{"event":"message.delivered","message_id":"msg-1"}';

    postNotifyWebhook($body, signature: str_repeat('0', 64))->assertUnauthorized();
    postNotifyWebhook($body, signature: hash_hmac('sha256', time().'.{"event":"message.failed"}', 'whsec_laravel_test'))->assertUnauthorized();
    postNotifyWebhook($body, timestamp: time() - 3600)->assertUnauthorized();
});

it('requires the webhook secret to be configured', function () {
    config(['notify.webhook.secret' => null]);
    app()->forgetInstance(WebhookVerifier::class);

    $this->withoutExceptionHandling();

    postNotifyWebhook('{"event":"message.delivered"}');
})->throws(ConfigurationException::class);
