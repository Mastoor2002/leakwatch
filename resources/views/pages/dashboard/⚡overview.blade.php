<?php

use App\Enums\FindingStatus;
use App\Enums\Severity;
use App\Models\Finding;
use App\Models\PackageFinding;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * @return Collection<int, Site>
     */
    #[Computed]
    public function sites(): Collection
    {
        return Auth::user()->currentTeam->sites()->with('client')->get();
    }

    #[Computed]
    public function stats(): array
    {
        $sites = $this->sites;
        $scored = $sites->whereNotNull('score');

        $counts = Finding::whereIn('site_id', $sites->pluck('id'))
            ->where('status', FindingStatus::Open)
            ->get()
            ->countBy(fn (Finding $finding) => $finding->severity->value);

        return [
            'sites' => $sites->count(),
            'verified' => $sites->whereNotNull('verified_at')->count(),
            'average' => $scored->isEmpty() ? null : (int) round($scored->avg('score')),
            'critical' => $counts[Severity::Critical->value] ?? 0,
            'high' => $counts[Severity::High->value] ?? 0,
            'medium' => $counts[Severity::Medium->value] ?? 0,
            'low' => $counts[Severity::Low->value] ?? 0,
            'packages' => PackageFinding::whereIn('site_id', $sites->pluck('id'))->where('status', FindingStatus::Open)->count(),
        ];
    }

    /**
     * @return Collection<int, Finding>
     */
    #[Computed]
    public function urgent(): Collection
    {
        return Finding::with('site')
            ->whereIn('site_id', $this->sites->pluck('id'))
            ->where('status', FindingStatus::Open)
            ->whereIn('severity', [Severity::Critical->value, Severity::High->value])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->sortByDesc(fn (Finding $finding) => $finding->severity->rank());
    }

    /**
     * @return Collection<int, Site>
     */
    #[Computed]
    public function needsAttention(): Collection
    {
        return $this->sites
            ->filter(fn (Site $site) => $site->grade === null || in_array($site->grade, ['D', 'F'], true) || ! $site->isVerified())
            ->sortBy(fn (Site $site) => $site->score ?? -1)
            ->take(6);
    }

    /**
     * @return Collection<int, Finding>
     */
    #[Computed]
    public function certificates(): Collection
    {
        return Finding::with('site')
            ->whereIn('site_id', $this->sites->pluck('id'))
            ->where('status', FindingStatus::Open)
            ->where('check_key', 'tls_certificate')
            ->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Security overview') }}</flux:heading>
        <flux:subheading>{{ auth()->user()->currentTeam->name }}</flux:subheading>
    </div>

    @if ($this->stats['sites'] === 0)
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600" data-test="dashboard-empty">
            <flux:icon.shield-check class="mx-auto size-12 text-zinc-400" />
            <flux:heading size="lg" class="mt-3">{{ __('Welcome to Leakwatch') }}</flux:heading>
            <flux:text class="mx-auto mt-1 max-w-xl">{{ __('Add the websites you look after. Leakwatch checks them from the outside for the mistakes attackers scan for - exposed .env files, debug mode, open admin dashboards, weak TLS - and watches their Composer packages for new vulnerabilities.') }}</flux:text>
            <flux:button variant="primary" icon="plus" class="mt-5" :href="route('sites.index')" wire:navigate>{{ __('Add your first site') }}</flux:button>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Sites monitored') }}</flux:text>
                <div class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $this->stats['sites'] }}</div>
                <flux:text class="text-sm">{{ __(':count verified', ['count' => $this->stats['verified']]) }}</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Average score') }}</flux:text>
                <div class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $this->stats['average'] ?? '—' }}</div>
                @if ($this->stats['average'] !== null)
                    <flux:badge size="sm" :color="\App\Scanning\Scoring::gradeColor(\App\Scanning\Scoring::grade($this->stats['average']))">{{ __('Grade :grade', ['grade' => \App\Scanning\Scoring::grade($this->stats['average'])]) }}</flux:badge>
                @endif
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Open issues') }}</flux:text>
                <div class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $this->stats['critical'] + $this->stats['high'] + $this->stats['medium'] + $this->stats['low'] }}</div>
                <div class="mt-1 flex flex-wrap gap-1">
                    <flux:badge size="sm" color="red">{{ $this->stats['critical'] }} {{ __('critical') }}</flux:badge>
                    <flux:badge size="sm" color="orange">{{ $this->stats['high'] }} {{ __('high') }}</flux:badge>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide">{{ __('Vulnerable packages') }}</flux:text>
                <div class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $this->stats['packages'] }}</div>
                <a href="{{ route('advisories.index') }}" wire:navigate class="text-sm underline">{{ __('See advisories') }}</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="space-y-3 lg:col-span-3">
                <flux:heading>{{ __('Critical & high issues') }}</flux:heading>
                @forelse ($this->urgent as $finding)
                    <a wire:key="urgent-{{ $finding->id }}" href="{{ route('sites.show', ['site' => $finding->site]) }}" wire:navigate class="flex items-start gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900" data-test="urgent-finding">
                        <flux:badge :color="$finding->severity->color()" size="sm">{{ $finding->severity->label() }}</flux:badge>
                        <div class="min-w-0">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $finding->title }}</div>
                            <flux:text class="text-sm">{{ $finding->site->host }} · {{ __('since :date', ['date' => $finding->created_at->diffForHumans()]) }}</flux:text>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
                        <flux:text>{{ __('No critical or high issues open.') }}</flux:text>
                    </div>
                @endforelse
            </div>

            <div class="space-y-6 lg:col-span-2">
                <div class="space-y-3">
                    <flux:heading>{{ __('Needs attention') }}</flux:heading>
                    @forelse ($this->needsAttention as $site)
                        <a wire:key="attention-{{ $site->id }}" href="{{ route('sites.show', ['site' => $site]) }}" wire:navigate class="flex items-center justify-between rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $site->host }}</div>
                                <flux:text class="text-xs">
                                    {{ $site->client->name }} ·
                                    @if (! $site->isVerified()) {{ __('not verified') }} @elseif ($site->grade === null) {{ __('never scanned') }} @else {{ __('score :score', ['score' => $site->score]) }} @endif
                                </flux:text>
                            </div>
                            <flux:badge :color="\App\Scanning\Scoring::gradeColor($site->grade)">{{ $site->grade ?? '–' }}</flux:badge>
                        </a>
                    @empty
                        <flux:text>{{ __('Every site is verified and scoring well.') }}</flux:text>
                    @endforelse
                </div>

                @if ($this->certificates->isNotEmpty())
                    <div class="space-y-3">
                        <flux:heading>{{ __('Certificate problems') }}</flux:heading>
                        @foreach ($this->certificates as $finding)
                            <a href="{{ route('sites.show', ['site' => $finding->site]) }}" wire:navigate class="block rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $finding->site->host }}</div>
                                <flux:text class="text-sm">{{ $finding->title }}</flux:text>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
