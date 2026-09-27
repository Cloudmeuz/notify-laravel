<?php

declare(strict_types=1);

use CloudMe\Notify\Debts\DebtsClient;
use CloudMe\Notify\NotifyClient;
use CloudMe\NotifyLaravel\Facades\Notify;

it('exposes the debts client through the facade', function () {
    expect(Notify::debts())->toBeInstanceOf(DebtsClient::class);
});

it('creates a debt and records a payment through the facade using a mocked HTTP layer', function () {
    $debt = [
        'id' => 'debt_1', 'external_id' => 'INV-1', 'name' => 'Aziz', 'phone' => '998901234567',
        'amount' => '1000.00', 'paid_amount' => '0.00', 'remaining_amount' => '1000.00', 'currency' => 'UZS',
        'due_date' => '2026-10-15', 'status' => 'active', 'environment' => 'sandbox',
    ];

    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(201, ['success' => true, 'debt' => $debt]),
        jsonResponse(201, [
            'success' => true,
            'payment' => ['id' => 'pay_1', 'amount' => '1000.00', 'paid_at' => '2026-10-16T10:00:00+05:00', 'attributed_channel' => 'telegram', 'days_late' => 1],
            'debt' => [...$debt, 'status' => 'paid', 'paid_amount' => '1000.00', 'remaining_amount' => '0.00', 'paid_channel' => 'telegram', 'paid_days_late' => 1],
        ]),
    ], $history);

    $this->app->instance(NotifyClient::class, $client);

    $created = Notify::debts()->create(name: 'Aziz', phone: '998901234567', amount: 1000, dueDate: '2026-10-15', externalId: 'INV-1');
    $result = Notify::debts()->recordPayment($created->id, 1000);

    expect($created->id)->toBe('debt_1')
        ->and($result->debt->isPaid())->toBeTrue()
        ->and($result->payment->attributedChannel)->toBe('telegram')
        ->and($history)->toHaveCount(3);
});
