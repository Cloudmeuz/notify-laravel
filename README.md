# CloudMe Notify for Laravel

Laravel integration for the [Notify](https://docs.notify.cloudme.uz) multi-channel notification API (SMS, Telegram, WhatsApp, Voice, Email, Push). This package is a thin Laravel layer on top of [`cloudme/notify-php`](../notify-php) - it does not reimplement authentication, RSA signing, retries, or channel sending. Every one of those is handled by the core SDK; this package only adds a service provider, a facade, Laravel-cache-backed token storage, and a Laravel Notifications channel.

## Requirements

- PHP >= 8.2
- Laravel >= 11.0 (tested against 11, 12, 13)
- `cloudme/notify-php` (installed automatically as a dependency)

## Installation

```bash
composer require cloudme/notify-laravel
```

The service provider and `Notify` facade are auto-discovered - no manual registration needed.

## Publish config

```bash
php artisan vendor:publish --tag=notify-config
```

This creates `config/notify.php`.

## Environment

Sandbox example:

```env
NOTIFY_ENVIRONMENT=sandbox
NOTIFY_CLIENT_ID=cmp_xxxxxxxxxxxxxxxx
NOTIFY_API_KEY=nk_test_xxxxxxxxxxxxxxxxxxxxxxxx
NOTIFY_PRIVATE_KEY_PATH=/secure/path/notify-sandbox.pem
NOTIFY_TOKEN_STORE=cache
NOTIFY_CACHE_STORE=redis
```

Switching to production later is just:

```env
NOTIFY_ENVIRONMENT=production
NOTIFY_CLIENT_ID=cmp_yyyyyyyyyyyyyyyy
NOTIFY_API_KEY=nk_live_yyyyyyyyyyyyyyyyyyyyyyyy
NOTIFY_PRIVATE_KEY_PATH=/secure/path/notify-production.pem
```

You never write a base URL yourself - `NOTIFY_ENVIRONMENT` picks it automatically:

| Environment | Base URL |
| --- | --- |
| `sandbox` | `https://sandbox.notify.cloudme.uz/api/v1` |
| `production` | `https://api.notify.cloudme.uz/api/v1` |

Notify's server enforces that your `client_id`/`api_key` actually belong to the environment you're calling - a sandbox client will never work against the production URL and vice versa (`ENVIRONMENT_MISMATCH`, HTTP 403). This package does not, and cannot, work around that; it always defers to the server.

Only set `NOTIFY_BASE_URL` for an advanced/custom deployment - when set, it overrides the environment-based URL above.

### Private key

Supply **either**:

- `NOTIFY_PRIVATE_KEY_PATH` - a filesystem path to a PEM file, or
- `NOTIFY_PRIVATE_KEY` - the PEM contents inline.

If both are set, **`NOTIFY_PRIVATE_KEY_PATH` wins**. A file path survives `.env` escaping (newlines, quoting) far more reliably than a multi-line inline PEM value does - prefer the path form unless you have a specific reason not to (e.g. a secrets manager that only injects env vars).

The private key is never logged, never included in an exception message, and never touched by `config:cache` in a way that would print it - `php artisan notify:doctor` (below) reports only whether a key is *configured and readable*, never its contents.

## Quick Start

### SMS

```php
use CloudMe\NotifyLaravel\Facades\Notify;

Notify::sms()->send(to: '+998901234567', message: 'Buyurtmangiz tayyor');
```

### Telegram

```php
Notify::telegram()->send(to: '123456789', message: 'Buyurtmangiz tayyor');
```

### WhatsApp

```php
Notify::whatsapp()->send(to: '+998901234567', message: 'Buyurtmangiz tayyor');
```

### Email

```php
Notify::email()->send(
    to: 'customer@example.com',
    subject: 'Buyurtma holati',
    message: 'Buyurtmangiz tayyor',
);
```

### Voice

```php
Notify::voice()->call(to: '+998901234567', message: 'Sizning buyurtmangiz tayyor.');
```

### Push

```php
Notify::push()->send(
    to: '+998901234567', // the customer's phone - the device is looked up server-side
    subject: 'Buyurtma tayyor',
    message: 'Buyurtmangiz tayyor',
);
```

Every `send()`/`call()` call returns `CloudMe\Notify\Responses\SendMessageResponse` (`messageId`, `status`, `price`, `currency`, `balance`, `environment`) - see [`cloudme/notify-php`](../notify-php)'s README for the full field reference.

## Templates

```php
Notify::sms()->send(
    to: '+998901234567',
    templateId: 42,
    variables: ['name' => 'Aziz', 'order_id' => '10293'],
);
```

## Push Device Registration

Call this whenever your mobile app obtains or rotates an FCM token for a logged-in user, before sending them a push notification:

```php
Notify::push()->registerDevice(
    phone: $user->phone,
    fcmToken: $request->fcm_token,
);
```

## Message Status

```php
$status = Notify::message($messageId);

$status->status;      // "moderation" | "queued" | "sent" | "delivered" | "failed" | "rejected" | ...
$status->deliveredAt;
```

### Moderation of free-text messages

A message sent with a template (`templateId`) goes out immediately. Plain text is checked against the platform's system templates and your company's own templates; if it matches one (the `{{variables}}` may hold any value) it goes out immediately too. Text matching **no** template is held until a Notify moderator approves it:

```php
$response = Notify::sms()->send(to: $phone, message: 'Summer sale -50%!');

$response->isHeldForModeration(); // status "moderation" - already charged, not yet sent
```

It then moves on to `queued` (approved) or `rejected` (refused and refunded; a `message.rejected` webhook is sent) - `Notify::message($id)->isRejected()`. For time-sensitive text, especially one-time codes, use a template or the OTP API below.

## One-time codes (OTP)

The platform generates, delivers and verifies the code - your app never stores it. Code length, validity and allowed attempts come from your company's OTP settings in the Notify cabinet, or the platform defaults when you have not set your own. OTP messages are billed like normal messages and never held for moderation.

```php
use CloudMe\Notify\Exceptions\RateLimitException;
use CloudMe\NotifyLaravel\Facades\Notify;

// Send
try {
    $otp = Notify::otp()->send(to: $request->phone); // channel: 'sms' (default), 'telegram', 'whatsapp', 'email'
} catch (RateLimitException $e) {
    return back()->withErrors(['phone' => "Try again in {$e->retryAfterSeconds} seconds."]); // OTP_RESEND_TOO_SOON
}

session(['otp_id' => $otp->otpId]); // $otp->expiresIn, $otp->codeLength, $otp->maxAttempts

// Verify
$result = Notify::otp()->verify(session('otp_id'), $request->code);

if ($result->verified) {
    // phone confirmed
} elseif ($result->canRetry()) {
    // wrong code - $result->attemptsLeft left
} else {
    // expired / attempts used up / already used ($result->errorCode) - send a new code
}
```

A wrong, expired or used-up code is returned as a result, not thrown. `send()` and `verify()` are never retried automatically (a retry could deliver a second code or count twice). In the sandbox, `$otp->code` holds the generated code so you can test the verify step.

## Balance

```php
$balance = Notify::balance();

$balance->balance;
$balance->currency;
```

## Reports

```php
use Illuminate\Support\Carbon;

Notify::reports()->daily(from: Carbon::today()->subDays(7), to: Carbon::today());
Notify::reports()->monthly();
```

`from`/`to` are optional on both - omit them for the endpoint's own default range.

## Laravel Notifications

```php
use CloudMe\NotifyLaravel\Notifications\NotifyChannel;
use CloudMe\NotifyLaravel\Notifications\NotifyMessage;
use Illuminate\Notifications\Notification;

class OrderReady extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via($notifiable): array
    {
        return [NotifyChannel::class];
    }

    public function toNotify($notifiable): NotifyMessage
    {
        return NotifyMessage::make()
            ->channel('sms')
            ->message("Buyurtma #{$this->order->id} tayyor");
    }
}
```

```php
$user->notify(new OrderReady($order));
```

### Who does it get sent to?

1. If `toNotify()` calls `->to(...)` explicitly, that value is used - always.
2. Otherwise, the notifiable's `routeNotificationForNotify()` method is called (standard Laravel notification routing, resolved via `Notifiable::routeNotificationFor('notify', $notification)`):

    ```php
    class User extends Authenticatable
    {
        use Notifiable;

        public function routeNotificationForNotify($notification = null): string
        {
            return $this->phone;
        }
    }
    ```

3. If neither is available, `CloudMe\NotifyLaravel\Exceptions\UnroutableNotifiableException` is thrown.

There is deliberately no third fallback that guesses a conventional property (`->phone`, `->email`, `->telegram_chat_id`, ...) - which one is actually correct depends on the channel you're sending on (a `whatsapp` send needs a phone, a `push` send also needs a phone but registered as a device, `telegram` needs a chat id), and a silently wrong guess is worse than the explicit exception above. If you send more than one channel to the same notifiable, either set `->to()` per notification or make `routeNotificationForNotify()` inspect `$notification` and branch.

### Templates and idempotency in a Notification

```php
public function toNotify($notifiable): NotifyMessage
{
    return NotifyMessage::make()
        ->channel('sms')
        ->template(42, ['name' => $notifiable->name])
        ->idempotencyKey((string) $this->order->id);
}
```

## Queued Notifications

Notify's own server already queues and dispatches messages to providers - this package does not add a second queue layer on top. Laravel's own `ShouldQueue` on your Notification class is all you need:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderReady extends Notification implements ShouldQueue
{
    use Queueable;

    // via() / toNotify() as above
}
```

```
Laravel Queue -> NotifyChannel::send() -> notify-php -> Notify API -> Notify's own queue -> Provider
```

## Error Handling

`cloudme/notify-php`'s typed exceptions are never wrapped or hidden - catch exactly the one you expect:

```php
use CloudMe\Notify\Exceptions\InsufficientBalanceException;
use CloudMe\Notify\Exceptions\ValidationException;
use CloudMe\Notify\Exceptions\RateLimitException;

try {
    Notify::sms()->send(to: $phone, message: $text);
} catch (InsufficientBalanceException $e) {
    // company balance too low
} catch (ValidationException $e) {
    $e->errors; // field => messages
} catch (RateLimitException $e) {
    $e->retryAfterSeconds;
}
```

`ForbiddenException` also covers an API client switched off because it exceeds your plan's API key limit (`CLIENT_REVOKED`). Also available: `AuthenticationException`, `ForbiddenException`, `NotFoundException`, `ServerException`, `NetworkException`, `ApiException` (fallback), and `ConfigurationException` (a purely local problem - bad/missing config, invalid private key - thrown before any request is even attempted).

## Sandbox

A sandbox API client (`nk_test_...`) only ever works against the sandbox base URL, and sandbox sends are never billed and never actually delivered - see `docs/openapi.yaml`'s Sandbox section in the main Notify repository. Set `NOTIFY_ENVIRONMENT=sandbox` and use sandbox credentials while integrating; nothing else in your code changes when you move to production.

## Production

Set `NOTIFY_ENVIRONMENT=production` and swap in your production `client_id`/`api_key`/private key. A production credential will never work against the sandbox URL, and a sandbox credential will never work against the production URL - Notify's server enforces this, not this package.

## Security

- **Never** commit a private key file into your repository or place it under `public/`. Keep it outside the web root, readable only by the application user (e.g. `chmod 600`).
- **Never** log `NOTIFY_API_KEY`, `NOTIFY_PRIVATE_KEY`, or a live `access_token`/`refresh_token`. This package never does so itself - `notify:doctor` reports only presence/readability, never values.
- Store all four of `client_id`, `api_key`, and the private key as environment secrets (`.env`, a secrets manager, your platform's encrypted config) - never hard-code them.
- A production `api_key` (`nk_live_...`) is exactly as sensitive as a password. Rotate it from the dashboard if you ever suspect it leaked.

## Token caching

Set `NOTIFY_TOKEN_STORE=cache` (the default) and point `NOTIFY_CACHE_STORE` at a shared store such as **Redis** - this is what lets every web request and every queue worker reuse the same access token instead of calling `/oauth/token` (which requires computing a fresh RSA signature) on every single call. `NOTIFY_TOKEN_STORE=array` keeps tokens in memory only for the current process/request - fine for a one-off script or a test, but a web app using it will re-authenticate constantly.

Cache keys are namespaced as `{prefix}:{environment}:{client_id}:tokens`, so sandbox and production tokens, and tokens for different API clients, are always stored separately and can never collide.

Writes are wrapped in an atomic cache lock when the underlying store supports one (Redis, Memcached, DynamoDB) to prevent a corrupted concurrent write. It does not eliminate a race where two requests both decide to refresh with the same still-valid refresh token at once - that decision is made inside `cloudme/notify-php`'s `TokenManager`, before either write reaches this package's storage, so it can't be arbitrated here. If it happens, Notify's reuse-detection revokes that pair server-side and the "losing" request gets an `AuthenticationException` from the refresh call - which `TokenManager` already catches and recovers from by fully re-authenticating. In practice this means one extra signed `/oauth/token` round trip under a race, not a failed send.

## Optional: `notify:doctor`

```bash
php artisan notify:doctor
```

Checks (never sends a real message, never prints a secret):

- environment is `production` or `sandbox`
- the resolved base URL
- `client_id` / `api_key` are configured
- the private key is configured and, if a path, actually readable
- the configured cache store is reachable (when `NOTIFY_TOKEN_STORE=cache`)

## Documentation

Full API reference (OpenAPI spec, request/response fields, error codes): <https://docs.notify.cloudme.uz>
