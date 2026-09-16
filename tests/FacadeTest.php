<?php

declare(strict_types=1);

use CloudMe\Notify\Channels\ChannelSender;
use CloudMe\Notify\NotifyClient;
use CloudMe\Notify\Responses\SendMessageResponse;
use CloudMe\NotifyLaravel\Facades\Notify;

it('proxies to the NotifyClient bound in the container', function () {
    expect(Notify::getFacadeRoot())->toBe($this->app->make(NotifyClient::class));
});

it('proxies channel methods to the real NotifyClient', function () {
    expect(Notify::sms())->toBeInstanceOf(ChannelSender::class);
});

it('proxies a real send through the facade using a mocked HTTP layer', function () {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, ['message_id' => 'msg_1', 'status' => 'queued', 'price' => '50', 'currency' => 'UZS', 'environment' => 'sandbox']),
    ], $history);

    $this->app->instance(NotifyClient::class, $client);

    $response = Notify::sms()->send(to: '+998901234567', message: 'Salom');

    expect($response)->toBeInstanceOf(SendMessageResponse::class)
        ->and($response->messageId)->toBe('msg_1')
        ->and($history)->toHaveCount(2);
});
