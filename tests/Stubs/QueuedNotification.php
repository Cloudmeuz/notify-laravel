<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Tests\Stubs;

use CloudMe\NotifyLaravel\Notifications\NotifyChannel;
use CloudMe\NotifyLaravel\Notifications\NotifyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class QueuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return [NotifyChannel::class];
    }

    public function toNotify(mixed $notifiable): NotifyMessage
    {
        return NotifyMessage::make()
            ->channel('sms')
            ->message('Buyurtmangiz tayyor');
    }
}
