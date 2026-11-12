<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A tiny custom notification channel that posts to a Slack incoming webhook.
 * Used with on-demand routing: Notification::route(SlackWebhookChannel::class, $url).
 */
class SlackWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $notifiable->routeNotificationFor(self::class, $notification);

        if (! is_string($url) || ! self::isValidWebhookUrl($url) || ! method_exists($notification, 'toSlackWebhook')) {
            return;
        }

        try {
            Http::timeout(10)->post($url, $notification->toSlackWebhook($notifiable));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Only real Slack webhook URLs are accepted, so this can't be abused to
     * make the server call arbitrary internal addresses.
     */
    public static function isValidWebhookUrl(?string $url): bool
    {
        return is_string($url) && (bool) preg_match('#^https://hooks\.slack\.com/services/[A-Za-z0-9/_-]+$#', $url);
    }
}
