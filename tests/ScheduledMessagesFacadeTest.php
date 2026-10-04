<?php

declare(strict_types=1);

use CloudMe\Notify\NotifyClient;
use CloudMe\Notify\ScheduledMessages\Recurrence;
use CloudMe\Notify\ScheduledMessages\ScheduledMessagesClient;
use CloudMe\NotifyLaravel\Facades\Notify;
use Illuminate\Support\Carbon;

it('exposes the scheduled messages client through the facade', function () {
    expect(Notify::scheduledMessages())->toBeInstanceOf(ScheduledMessagesClient::class);
});

it('schedules and cancels a message through the facade using a mocked HTTP layer', function () {
    $schedule = [
        'id' => 'schedule_1', 'status' => 'active', 'environment' => 'sandbox', 'channel' => 'sms',
        'recipient' => '998901234567', 'message' => 'Eslatma', 'schedule_type' => 'recurring',
        'starts_at' => '2026-10-05T09:00:00+05:00', 'recurrence_type' => 'weekly', 'weekdays' => [1, 4],
        'next_run_at' => '2026-10-05T09:00:00+05:00',
    ];

    $history = [];
    $client = mockedNotifyClient([
        jsonResponse(200, tokenResponseBody()),
        jsonResponse(201, ['success' => true, 'scheduled_message' => $schedule]),
        jsonResponse(200, ['success' => true, 'scheduled_message' => [...$schedule, 'status' => 'cancelled', 'next_run_at' => null]]),
    ], $history);

    $this->app->instance(NotifyClient::class, $client);

    $created = Notify::scheduledMessages()->create(
        channel: 'sms',
        startsAt: Carbon::parse('2026-10-05 09:00:00', 'Asia/Tashkent'),
        recipient: '998901234567',
        message: 'Eslatma',
        recurrence: Recurrence::weekly([1, 4]),
    );
    $cancelled = Notify::scheduledMessages()->cancel($created->id);

    expect($created->nextRunAt)->toBe('2026-10-05T09:00:00+05:00')
        ->and($cancelled->status)->toBe('cancelled')
        ->and(json_decode((string) $history[1]['request']->getBody(), true)['starts_at'])->toBe('2026-10-05T09:00:00+05:00')
        ->and($history)->toHaveCount(3);
});
