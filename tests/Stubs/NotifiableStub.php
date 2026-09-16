<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Tests\Stubs;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

final class NotifiableStub
{
    use Notifiable;

    public function routeNotificationForNotify(?Notification $notification = null): ?string
    {
        return '+998901111111';
    }
}
