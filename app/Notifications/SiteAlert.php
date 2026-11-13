<?php

namespace App\Notifications;

use App\Models\Site;
use App\Notifications\Channels\SlackWebhookChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Shared delivery for Surfacewatch alerts: email to the team's owners/admins,
 * plus the team's Slack webhook when one is configured.
 */
abstract class SiteAlert extends Notification
{
    public function __construct(public Site $site)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable
            ? [SlackWebhookChannel::class]
            : ['mail'];
    }

    protected function siteUrl(): string
    {
        return route('sites.show', ['current_team' => $this->site->team->slug, 'site' => $this->site->id]);
    }

    protected function deliver(): void
    {
        $team = $this->site->team;

        if ($team === null || ! $team->alerts_enabled) {
            return;
        }

        NotificationFacade::send($team->alertRecipients(), $this);

        if (SlackWebhookChannel::isValidWebhookUrl($team->slack_webhook_url)) {
            NotificationFacade::route(SlackWebhookChannel::class, $team->slack_webhook_url)->notify($this);
        }
    }
}
