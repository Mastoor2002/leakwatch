<?php

use App\Enums\FindingStatus;
use App\Models\PackageFinding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Advisories')] class extends Component {
    /**
     * Open vulnerable-package findings across the whole team, grouped by advisory:
     * "which of our apps are affected by this?"
     *
     * @return Collection<int, Collection<int, PackageFinding>>
     */
    #[Computed]
    public function groups(): Collection
    {
        $teamId = Auth::user()->current_team_id;

        return PackageFinding::query()
            ->with(['advisory', 'site.client'])
            ->where('status', FindingStatus::Open)
            ->whereHas('site', fn ($query) => $query->where('team_id', $teamId))
            ->get()
            ->groupBy('advisory_id')
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->values();
    }

    #[Computed]
    public function reportingSites(): int
    {
        return Auth::user()->currentTeam->sites()->whereNotNull('agent_last_seen_at')->count();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Advisories') }}</flux:heading>
        <flux:subheading>{{ __('Known vulnerabilities in the Composer packages your sites run, across every client.') }}</flux:subheading>
    </div>

    @if ($this->groups->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:icon.shield-check class="mx-auto size-10 text-green-500" />
            <flux:heading class="mt-3">{{ __('No open advisories') }}</flux:heading>
            <flux:text class="mt-1">
                {{ trans_choice(':count site reports its packages through the agent.|:count sites report their packages through the agent.', $this->reportingSites, ['count' => $this->reportingSites]) }}
                {{ __('Install the agent on more sites from each site\'s Agent tab.') }}
            </flux:text>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->groups as $group)
                @php($advisory = $group->first()->advisory)
                <div wire:key="advisory-{{ $advisory->id }}" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="advisory-group">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge :color="$advisory->severityColor()" size="sm">{{ ucfirst($advisory->severity ?? 'unknown') }}</flux:badge>
                                <span class="font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ $advisory->package_name }}</span>
                                @if ($advisory->cve)
                                    <flux:badge size="sm">{{ $advisory->cve }}</flux:badge>
                                @endif
                            </div>
                            <flux:heading class="mt-1">{{ $advisory->title }}</flux:heading>
                            <flux:text class="text-sm">{{ __('Affected versions: :range', ['range' => $advisory->affected_versions]) }}</flux:text>
                        </div>
                        <div class="shrink-0 text-end">
                            <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $group->count() }}</div>
                            <flux:text class="text-xs">{{ trans_choice('site affected|sites affected', $group->count()) }}</flux:text>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($group as $finding)
                            <a href="{{ route('sites.show', ['site' => $finding->site_id, 'tab' => 'dependencies']) }}" wire:navigate class="rounded-md border border-zinc-200 px-2 py-1 text-sm hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                {{ $finding->site->host }} <span class="text-zinc-500">· {{ $finding->site->client->name }} · {{ $finding->installed_version }}</span>
                            </a>
                        @endforeach
                    </div>

                    @if ($advisory->link)
                        <a href="{{ $advisory->link }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-sm underline">{{ __('Read the advisory') }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
