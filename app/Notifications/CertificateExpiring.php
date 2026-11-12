<?php

namespace App\Notifications;

use App\Models\Site;
use App\Scanning\Data\TlsCertificate;
use Illuminate\Notifications\Messages\MailMessage;

class CertificateExpiring extends SiteAlert
{
    public function __construct(Site $site, public string $expiresOn, public int $daysRemaining)
    {
        parent::__construct($site);
    }

    public static function send(Site $site, TlsCertificate $certificate): void
    {
        (new self($site, $certificate->validTo->toDateString(), $certificate->daysRemaining()))->deliver();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Leakwatch] TLS certificate for {$this->site->host} expires in {$this->daysRemaining} days")
            ->line("The certificate for {$this->site->host} expires on {$this->expiresOn}.")
            ->line('Automatic renewal may be broken. Renew it before visitors see a browser warning.')
            ->action('Open site', $this->siteUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(object $notifiable): array
    {
        return ['text' => ":lock: TLS certificate for *{$this->site->host}* expires in {$this->daysRemaining} days ({$this->expiresOn})."];
    }
}
