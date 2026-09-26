<?php

declare(strict_types=1);

use CloudMe\Notify\NotifyClient;
use CloudMe\Notify\Otp\OtpClient;
use CloudMe\Notify\Responses\OtpSendResponse;
use CloudMe\NotifyLaravel\Facades\Notify;

it('exposes the otp client through the facade', function () {
    expect(Notify::otp())->toBeInstanceOf(OtpClient::class);
});

it('sends and verifies an otp through the facade using a mocked HTTP layer', function () {
    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(200, [
            'otp_id' => 'otp_1', 'environment' => 'sandbox', 'channel' => 'sms', 'recipient' => '998901234567', 'status' => 'pending',
            'code_length' => 6, 'max_attempts' => 3, 'expires_in' => 300, 'message_id' => 'msg_1', 'price' => '120', 'currency' => 'UZS', 'code' => '481201',
        ]),
        jsonResponse(200, ['verified' => true, 'otp_id' => 'otp_1', 'status' => 'verified']),
    ], $history);

    $this->app->instance(NotifyClient::class, $client);

    $otp = Notify::otp()->send(to: '998901234567');

    expect($otp)->toBeInstanceOf(OtpSendResponse::class)
        ->and($otp->otpId)->toBe('otp_1')
        ->and(Notify::otp()->verify($otp->otpId, (string) $otp->code)->verified)->toBeTrue()
        ->and($history)->toHaveCount(3);
});
