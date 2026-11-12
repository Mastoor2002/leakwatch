<?php

use App\Enums\FindingStatus;
use App\Models\Client;
use App\Models\Site;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sites')] class extends Component {
    public string $url = '';

    public string $clientId = '';

    public string $newClientName = '';

    public string $search = '';

    public function addSite(): void
    {
        Gate::authorize('create', Site::class);

        $team = Auth::user()->currentTeam;
        $normalized = Site::normalizeUrl($this->url);

        $this->validate([
            'url' => ['required', 'string', 'max:255'],
            'clientId' => ['required_without:newClientName', 'nullable', Rule::exists('clients', 'id')->where('team_id', $team->id)],
            'newClientName' => ['required_without:clientId', 'nullable', 'string', 'max:255'],
        ], [
            'clientId.required_without' => __('Pick a client or type a new client name.'),
            'newClientName.required_without' => __('Pick a client or type a new client name.'),
        ]);

        if ($normalized === null) {
            $this->addError('url', __('Enter a valid http(s) address, like https://example.com'));

            return;
        }

        $host = (string) parse_url($normalized, PHP_URL_HOST);

        if ($team->sites()->where('host', $host)->exists()) {
            $this->addError('url', __(':host is already being monitored by this team.', ['host' => $host]));

            return;
        }

        $client = $this->clientId !== ''
            ? $team->clients()->findOrFail($this->clientId)
            : $team->clients()->create(['name' => $this->newClientName]);

        $site = $team->sites()->create([
            'client_id' => $client->id,
            'url' => $normalized,
            'host' => $host,
        ]);

        $this->dispatch('close-modal', name: 'add-site');
        $this->reset('url', 'clientId', 'newClientName');

        Flux::toast(variant: 'success', text: __('Site added. Verify ownership, then run your first scan.'));

        $this->redirectRoute('sites.show', ['site' => $site->id], navigate: true);
    }

    /**
     * @return Collection<int, Site>
     */
    #[Computed]
    public function sites(): Collection
    {
        return Auth::user()->currentTeam->sites()
            ->with('client')
            ->withCount(['findings as open_findings_count' => fn ($query) => $query->where('status', FindingStatus::Open)])
            ->withCount(['packageFindings as open_package_findings_count' => fn ($query) => $query->where('status', FindingStatus::Open)])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('host', 'like', '%'.$this->search.'%')
                ->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$this->search.'%'))))
            ->orderByRaw('score is null')
            ->orderBy('score')
            ->orderBy('host')
            ->get();
    }

    /**
     * @return Collection<int, Client>
     */
    #[Computed]
    public function clients(): Collection
    {
        return Auth::user()->currentTeam->clients()->orderBy('name')->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Sites') }}</flux:heading>
            <flux:subheading>{{ __('Every website your team monitors, worst score first.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search sites or clients')" size="sm" />
            <flux:modal.trigger name="add-site">
                <flux:button variant="primary" icon="plus" size="sm" data-test="add-site-button">{{ __('Add site') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->sites->isEmpty() && $search === '')
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:icon.globe-alt class="mx-auto size-10 text-zinc-400" />
            <flux:heading class="mt-3">{{ __('No sites yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add a website to start monitoring it for exposed secrets, debug mode and vulnerable packages.') }}</flux:text>
            <flux:modal.trigger name="add-site">
                <flux:button variant="primary" icon="plus" class="mt-4">{{ __('Add your first site') }}</flux:button>
            </flux:modal.trigger>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Site') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Grade') }}</flux:table.column>
                <flux:table.column>{{ __('Open issues') }}</flux:table.column>
                <flux:table.column>{{ __('Vulnerable packages') }}</flux:table.column>
                <flux:table.column>{{ __('Last scan') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->sites as $site)
                    <flux:table.row :key="$site->id" data-test="site-row">
                        <flux:table.cell>
                            <a href="{{ route('sites.show', ['site' => $site]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $site->host }}</a>
                            <div class="mt-0.5 flex gap-1">
                                @if ($site->isVerified())
                                    <flux:badge color="green" size="sm">{{ __('Verified') }}</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">{{ __('Unverified') }}</flux:badge>
                                @endif
                                @if ($site->is_laravel)
                                    <flux:badge color="red" size="sm">Laravel</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $site->client->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($site->grade)
                                <flux:badge :color="\App\Scanning\Scoring::gradeColor($site->grade)">{{ $site->grade }} · {{ $site->score }}</flux:badge>
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $site->open_findings_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($site->open_package_findings_count > 0)
                                <flux:badge color="red" size="sm">{{ $site->open_package_findings_count }}</flux:badge>
                            @else
                                {{ $site->agent_last_seen_at ? '0' : '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $site->last_scanned_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">{{ __('No sites match ":search".', ['search' => $search]) }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="add-site" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="addSite" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add a site') }}</flux:heading>
                <flux:subheading>{{ __('You\'ll verify ownership before Leakwatch probes anything beyond the homepage.') }}</flux:subheading>
            </div>

            <flux:input wire:model="url" :label="__('Website address')" placeholder="https://example.com" required autofocus data-test="site-url" />

            @if ($this->clients->isNotEmpty())
                <flux:select wire:model.live="clientId" :label="__('Client')" data-test="site-client">
                    <flux:select.option value="">{{ __('— New client —') }}</flux:select.option>
                    @foreach ($this->clients as $client)
                        <flux:select.option :value="$client->id">{{ $client->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($clientId === '')
                <flux:input wire:model="newClientName" :label="__('New client name')" :placeholder="__('e.g. Acme Bakery')" data-test="site-new-client" />
            @endif

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" data-test="add-site-submit">{{ __('Add site') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
