<?php

use App\Notifications\Channels\SlackWebhookChannel;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alerts')] class extends Component {
    public bool $alertsEnabled = true;

    public string $slackWebhookUrl = '';

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        $this->alertsEnabled = (bool) $team->alerts_enabled;
        $this->slackWebhookUrl = (string) $team->slack_webhook_url;
    }

    public function save(): void
    {
        $team = Auth::user()->currentTeam;
        Gate::authorize('update', $team);

        $this->validate([
            'slackWebhookUrl' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== '' && ! SlackWebhookChannel::isValidWebhookUrl($value)) {
                    $fail(__('Use a Slack incoming webhook URL that starts with https://hooks.slack.com/services/'));
                }
            }],
        ]);

        $team->update([
            'alerts_enabled' => $this->alertsEnabled,
            'slack_webhook_url' => $this->slackWebhookUrl ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Alert settings saved.'));
    }

    public function canEdit(): bool
    {
        return Auth::user()->can('update', Auth::user()->currentTeam);
    }
}; ?>

<div class="flex w-full max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Alerts') }}</flux:heading>
        <flux:subheading>{{ __('Who hears about new problems, and where.') }}</flux:subheading>
    </div>

    <flux:callout icon="bell">
        <flux:callout.text>
            {{ __('Team owners and admins get an email when a scan finds a new critical or high issue, when a TLS certificate is about to expire, and when a package on one of your sites gets a security advisory. Each problem is only alerted once.') }}
        </flux:callout.text>
    </flux:callout>

    <form wire:submit="save" class="space-y-6">
        <flux:switch wire:model="alertsEnabled" :label="__('Send alerts for this team')" :disabled="! $this->canEdit()" />

        <flux:input wire:model="slackWebhookUrl" :label="__('Slack incoming webhook (optional)')" placeholder="https://hooks.slack.com/services/..." :disabled="! $this->canEdit()" />

        @if ($this->canEdit())
            <flux:button type="submit" variant="primary" data-test="alerts-save">{{ __('Save') }}</flux:button>
        @else
            <flux:text class="text-sm">{{ __('Only team owners and admins can change alert settings.') }}</flux:text>
        @endif
    </form>
</div>
