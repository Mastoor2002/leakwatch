<?php

namespace App\Notifications;

use App\Models\Finding;
use App\Models\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

class NewFindingsFound extends SiteAlert
{
    /**
     * @param  Collection<int, Finding>  $findings
     */
    public function __construct(Site $site, public Collection $findings)
    {
        parent::__construct($site);
    }

    /**
     * @param  Collection<int, Finding>  $findings
     */
    public static function send(Site $site, Collection $findings): void
    {
        (new self($site, $findings))->deliver();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->error()
            ->subject("[Surfacewatch] {$this->findings->count()} new security issue(s) on {$this->site->host}")
            ->greeting("New issues on {$this->site->host}")
            ->line('The latest scan found problems that need attention:');

        foreach ($this->findings->take(10) as $finding) {
            $message->line("• [{$finding->severity->label()}] {$finding->title}");
        }

        return $message
            ->action('Open the site report', $this->siteUrl())
            ->line('Each finding includes step-by-step fix instructions.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(object $notifiable): array
    {
        $lines = $this->findings->take(10)
            ->map(fn (Finding $finding) => "• *{$finding->severity->label()}* {$finding->title}")
            ->implode("\n");

        return [
            'text' => ":rotating_light: *{$this->findings->count()} new security issue(s) on {$this->site->host}*\n{$lines}\n<{$this->siteUrl()}|Open in Surfacewatch>",
        ];
    }
}
