<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Tests\Stubs;

use CloudMe\NotifyLaravel\Notifications\NotifyChannel;
use CloudMe\NotifyLaravel\Notifications\NotifyMessage;
use Illuminate\Notifications\Notification;

final class ChannelNotification extends Notification
{
    public function __construct(private readonly string $channel) {}

    public function via(mixed $notifiable): array
    {
        return [NotifyChannel::class];
    }

    public function toNotify(mixed $notifiable): NotifyMessage
    {
        return NotifyMessage::make()
            ->channel($this->channel)
            ->message('Buyurtmangiz tayyor');
    }
}
