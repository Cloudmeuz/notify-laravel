<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Tests\Stubs;

use Illuminate\Notifications\Notifiable;

/**
 * Uses the standard Notifiable trait but defines no routeNotificationForNotify() -
 * used to prove NotifyChannel throws a clear error instead of guessing.
 */
final class UnroutableNotifiableStub
{
    use Notifiable;
}
