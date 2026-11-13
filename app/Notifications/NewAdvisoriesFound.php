<?php

namespace App\Notifications;

use App\Models\PackageFinding;
use App\Models\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

class NewAdvisoriesFound extends SiteAlert
{
    /**
     * @param  Collection<int, PackageFinding>  $packageFindings
     */
    public function __construct(Site $site, public Collection $packageFindings)
    {
        parent::__construct($site);
    }

    /**
     * @param  Collection<int, PackageFinding>  $packageFindings
     */
    public static function send(Site $site, Collection $packageFindings): void
    {
        $packageFindings->each->loadMissing('advisory');

        (new self($site, $packageFindings))->deliver();

        PackageFinding::whereIn('id', $packageFindings->pluck('id'))->update(['notified_at' => now()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->error()
            ->subject("[Surfacewatch] Vulnerable packages on {$this->site->host}")
            ->greeting("Vulnerable dependencies on {$this->site->host}")
            ->line('These installed packages have published security advisories:');

        foreach ($this->packageFindings->take(10) as $finding) {
            $message->line("• {$finding->package_name} {$finding->installed_version}: {$finding->advisory->title}");
        }

        return $message->action('See affected packages', $this->siteUrl().'?tab=dependencies');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(object $notifiable): array
    {
        $lines = $this->packageFindings->take(10)
            ->map(fn (PackageFinding $finding) => "• `{$finding->package_name}` {$finding->installed_version} - {$finding->advisory->title}")
            ->implode("\n");

        return ['text' => ":package: *Vulnerable packages on {$this->site->host}*\n{$lines}"];
    }
}
