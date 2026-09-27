<?php

declare(strict_types=1);

use CloudMe\Notify\Exceptions\InsufficientBalanceException;
use CloudMe\Notify\NotifyClient;
use CloudMe\NotifyLaravel\Exceptions\UnroutableNotifiableException;
use CloudMe\NotifyLaravel\Notifications\NotifyChannel;
use CloudMe\NotifyLaravel\Notifications\NotifyMessage;
use CloudMe\NotifyLaravel\Tests\Stubs\ChannelNotification;
use CloudMe\NotifyLaravel\Tests\Stubs\ExplicitRecipientNotification;
use CloudMe\NotifyLaravel\Tests\Stubs\NotifiableStub;
use CloudMe\NotifyLaravel\Tests\Stubs\QueuedNotification;
use CloudMe\NotifyLaravel\Tests\Stubs\UnroutableNotifiableStub;
use Illuminate\Notifications\Notification;

function sendResponseBody(string $environment = 'sandbox'): array
{
    return ['message_id' => 'msg_1', 'status' => 'queued', 'price' => '50', 'currency' => 'UZS', 'environment' => $environment];
}

dataset('channels', ['sms', 'telegram', 'whatsapp', 'voice', 'email', 'push']);

it('sends through each supported channel', function (string $channel) {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, sendResponseBody()),
    ], $history);

    $notifyChannel = new NotifyChannel($client);
    $notifiable = new NotifiableStub;
    $notification = new ChannelNotification($channel);

    $response = $notifyChannel->send($notifiable, $notification);

    expect($response->messageId)->toBe('msg_1')
        ->and((string) $history[1]['request']->getUri())->toContain("messages/{$channel}");
})->with('channels');

it('routes to an explicit NotifyMessage::to() over routeNotificationForNotify', function () {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, sendResponseBody()),
    ], $history);

    $notifyChannel = new NotifyChannel($client);
    $notifiable = new NotifiableStub; // routes to +998901111111 if asked
    $notification = new ExplicitRecipientNotification('+998909999999');

    $notifyChannel->send($notifiable, $notification);

    $body = json_decode((string) $history[1]['request']->getBody(), true);

    expect($body['to'])->toBe('+998909999999');
});

it('falls back to routeNotificationForNotify when no explicit recipient is set', function () {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, sendResponseBody()),
    ], $history);

    $notifyChannel = new NotifyChannel($client);
    $notifiable = new NotifiableStub;
    $notification = new ChannelNotification('sms');

    $notifyChannel->send($notifiable, $notification);

    $body = json_decode((string) $history[1]['request']->getBody(), true);

    expect($body['to'])->toBe('+998901111111');
});

it('throws when no recipient can be determined at all', function () {
    $history = [];
    $client = mockedNotifyClient([], $history);

    $notifyChannel = new NotifyChannel($client);
    $notifiable = new UnroutableNotifiableStub;
    $notification = new ChannelNotification('sms');

    expect(fn () => $notifyChannel->send($notifiable, $notification))
        ->toThrow(UnroutableNotifiableException::class);
});

it('propagates typed core SDK exceptions to the caller', function () {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(402, ['error' => ['code' => 'INSUFFICIENT_BALANCE', 'message' => 'Not enough balance.']]),
    ], $history);

    $notifyChannel = new NotifyChannel($client);
    $notifiable = new NotifiableStub;
    $notification = new ChannelNotification('sms');

    expect(fn () => $notifyChannel->send($notifiable, $notification))
        ->toThrow(InsufficientBalanceException::class);
});

it('works as a queued notification end to end through the container', function () {
    config(['queue.default' => 'sync']);

    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, sendResponseBody()),
    ], $history);

    $this->app->instance(NotifyClient::class, $client);

    $notifiable = new NotifiableStub;
    $notifiable->notify(new QueuedNotification);

    expect($history)->toHaveCount(2)
        ->and((string) $history[1]['request']->getUri())->toContain('messages/sms');
});

it('sends a photo url set with NotifyMessage::photo()', function () {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, sendResponseBody()),
    ], $history);

    $notification = new class extends Notification
    {
        public function toNotify(mixed $notifiable): NotifyMessage
        {
            return NotifyMessage::make()
                ->channel('telegram')
                ->message('<b>Buyurtmangiz tayyor</b>')
                ->photo('https://example.com/order.jpg');
        }
    };

    (new NotifyChannel($client))->send(new NotifiableStub, $notification);

    $body = json_decode((string) $history[1]['request']->getBody(), true);

    expect($body['photo_url'])->toBe('https://example.com/order.jpg')
        ->and($body['message'])->toBe('<b>Buyurtmangiz tayyor</b>');
});
