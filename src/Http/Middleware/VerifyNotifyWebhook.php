<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Http\Middleware;

use Closure;
use CloudMe\Notify\Exceptions\InvalidWebhookSignatureException;
use CloudMe\Notify\Webhooks\WebhookEvent;
use CloudMe\Notify\Webhooks\WebhookVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware (alias "notify.webhook") that rejects any request not
 * signed with your NOTIFY_WEBHOOK_SECRET and hands the verified event to
 * the controller:
 *
 * ```php
 * Route::post('/webhooks/notify', NotifyWebhookController::class)->middleware('notify.webhook');
 *
 * // in the controller:
 * $event = VerifyNotifyWebhook::event($request); // CloudMe\Notify\Webhooks\WebhookEvent
 * ```
 *
 * Exclude the route from CSRF verification - Notify cannot send a token.
 */
final class VerifyNotifyWebhook
{
    public const ATTRIBUTE = 'notify_webhook_event';

    public function __construct(private readonly WebhookVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = $this->verifier->parse($request->getContent(), $request->headers->all());
        } catch (InvalidWebhookSignatureException) {
            abort(401, 'Invalid Notify webhook signature.');
        }

        $request->attributes->set(self::ATTRIBUTE, $event);

        return $next($request);
    }

    public static function event(Request $request): WebhookEvent
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }
}
