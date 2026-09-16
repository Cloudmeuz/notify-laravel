<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Exceptions;

use RuntimeException;

/**
 * Thrown when NotifyChannel cannot work out who to send a message to: the
 * notification's NotifyMessage did not set ->to(), and the notifiable has no
 * routeNotificationForNotify() method to ask instead.
 *
 * Deliberately not guessed from a conventional property (e.g. ->phone,
 * ->email) - which of those is right depends on the channel, and a silent
 * wrong guess is worse than a clear error here.
 */
final class UnroutableNotifiableException extends RuntimeException {}
