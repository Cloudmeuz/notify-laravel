<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Notifications;

use CloudMe\Notify\NotifyClient;
use CloudMe\Notify\Responses\SendMessageResponse;
use CloudMe\NotifyLaravel\Exceptions\UnroutableNotifiableException;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Laravel Notification channel driver for Notify.
 *
 * ```php
 * public function via($notifiable): array
 * {
 *     return [NotifyChannel::class];
 * }
 *
 * public function toNotify($notifiable): NotifyMessage
 * {
 *     return NotifyMessage::make()->channel('sms')->message('Buyurtmangiz tayyor');
 * }
 * ```
 *
 * Sending is delegated entirely to cloudme/notify-php's NotifyClient - this
 * class only builds the request from the NotifyMessage and works out who to
 * send it to.
 */
final class NotifyChannel
{
    public function __construct(private readonly NotifyClient $client) {}

    public function send(mixed $notifiable, Notification $notification): SendMessageResponse
    {
        if (! method_exists($notification, 'toNotify')) {
            throw new LogicException(sprintf(
                '%s must define a toNotify(): %s method to be sent through the Notify channel.',
                $notification::class,
                NotifyMessage::class,
            ));
        }

        $message = $notification->toNotify($notifiable);

        if (! $message instanceof NotifyMessage) {
            throw new LogicException(sprintf(
                '%s::toNotify() must return a %s instance, got %s.',
                $notification::class,
                NotifyMessage::class,
                get_debug_type($message),
            ));
        }

        $channel = $message->getChannel();

        if ($channel === null || $channel === '') {
            throw new LogicException(sprintf(
                '%s::toNotify() must set a channel via NotifyMessage::channel().',
                $notification::class,
            ));
        }

        $to = $this->resolveRecipient($notifiable, $message, $notification);

        return $this->client->channel($channel)->send(
            to: $to,
            message: $message->getMessage(),
            templateId: $message->getTemplateId(),
            variables: $message->getVariables(),
            smsType: $message->getSmsType(),
            subject: $message->getSubject(),
            idempotencyKey: $message->getIdempotencyKey(),
            photoUrl: $message->getPhotoUrl(),
        );
    }

    /**
     * Priority: an explicit NotifyMessage::to(), then the notifiable's
     * routeNotificationForNotify(). Deliberately does not fall back to
     * guessing a conventional property (->phone, ->email, ...) - which one
     * is correct depends on the channel being sent, and a silent wrong
     * guess is worse than the clear error thrown below.
     */
    private function resolveRecipient(mixed $notifiable, NotifyMessage $message, Notification $notification): string
    {
        if ($message->getTo() !== null && $message->getTo() !== '') {
            return $message->getTo();
        }

        $route = null;

        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('notify', $notification);
        } elseif (method_exists($notifiable, 'routeNotificationForNotify')) {
            $route = $notifiable->routeNotificationForNotify($notification);
        }

        if (is_string($route) && $route !== '') {
            return $route;
        }

        throw new UnroutableNotifiableException(sprintf(
            'Could not determine a Notify recipient for %s. Set NotifyMessage::to() explicitly in toNotify(), '.
            'or define routeNotificationForNotify() on the notifiable.',
            is_object($notifiable) ? $notifiable::class : get_debug_type($notifiable),
        ));
    }
}
