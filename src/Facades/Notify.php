<?php

declare(strict_types=1);

namespace CloudMe\NotifyLaravel\Facades;

use CloudMe\Notify\NotifyClient;
use Illuminate\Support\Facades\Facade;

/**
 * Thin proxy to the NotifyClient singleton bound by NotifyServiceProvider -
 * every method here is really cloudme/notify-php's NotifyClient, not a
 * reimplementation.
 *
 * @method static \CloudMe\Notify\Channels\ChannelSender sms()
 * @method static \CloudMe\Notify\Channels\ChannelSender telegram()
 * @method static \CloudMe\Notify\Channels\ChannelSender whatsapp()
 * @method static \CloudMe\Notify\Channels\ChannelSender voice()
 * @method static \CloudMe\Notify\Channels\ChannelSender email()
 * @method static \CloudMe\Notify\Channels\PushChannelSender push()
 * @method static \CloudMe\Notify\Channels\ChannelSender channel(string $channel)
 * @method static \CloudMe\Notify\Responses\MessageStatusResponse message(string $messageId)
 * @method static \CloudMe\Notify\Responses\BalanceResponse balance()
 * @method static \CloudMe\Notify\Reports\ReportsClient reports()
 * @method static \CloudMe\Notify\Otp\OtpClient otp()
 * @method static \CloudMe\Notify\Debts\DebtsClient debts()
 *
 * @see NotifyClient
 */
final class Notify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotifyClient::class;
    }
}
