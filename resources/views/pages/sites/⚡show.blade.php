<?php

use App\Ai\RemediationAdvisor;
use App\Enums\FindingStatus;
use App\Enums\ScanFrequency;
use App\Enums\Severity;
use App\Models\Finding;
use App\Models\PackageFinding;
use App\Models\Scan;
use App\Models\Site;
use App\Scanning\ScanLauncher;
use App\Scanning\ScanRunner;
use App\Scanning\Scoring;
use App\Scanning\SiteVerifier;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Site')] class extends Component {
    public Site $site;

    #[Url]
    public string $tab = 'findings';

    public bool $showClosed = false;

    public ?int $ignoringId = null;

    public string $ignoreReason = '';

    public ?string $newToken = null;

    public string $scanFrequency = 'weekly';

    /** @var array<int, string> */
    public array $explanations = [];

    public function mount(Site $site): void
    {
        Gate::authorize('view', $site);

        abort_unless((int) $site->team_id === (int) Auth::user()->current_team_id, 404);

        $this->site = $site;
        $this->scanFrequency = $site->scan_frequency->value;
    }

    public function scan(ScanLauncher $launcher, ScanRunner $runner): void
    {
        Gate::authorize('scan', $this->site);

        $scan = $launcher->launch($this->site);

        if (! ScanLauncher::usesQueue()) {
            $runner->step($scan);
        }

        $this->tab = 'findings';
        unset($this->activeScan);
    }

    /**
     * Called by wire:poll while a scan is active. With the inline driver each
     * poll runs the next check, so no queue worker is needed.
     */
    public function tick(ScanRunner $runner): void
    {
        $scan = $this->site->activeScan();

        if ($scan !== null && ! ScanLauncher::usesQueue()) {
            $runner->step($scan);
        }

        $this->site->refresh();
        unset($this->activeScan, $this->lastScan, $this->findings);

        if ($scan !== null && ! $scan->fresh()->isActive()) {
            $fresh = $scan->fresh();

            $fresh->status->value === 'failed'
                ? Flux::toast(variant: 'danger', text: __('Scan failed: :error', ['error' => $fresh->error]))
                : Flux::toast(variant: 'success', text: __('Scan complete: grade :grade.', ['grade' => $fresh->grade]));
        }
    }

    public function verify(SiteVerifier $verifier): void
    {
        Gate::authorize('update', $this->site);

        $method = $verifier->verify($this->site);
        $this->site->refresh();

        $method
            ? Flux::toast(variant: 'success', text: __('Ownership verified via :method. All checks are now enabled.', ['method' => $method->label()]))
            : Flux::toast(variant: 'danger', text: __('Could not find the DNS record or verification file yet.').($verifier->lastError ? ' '.$verifier->lastError : ''));
    }

    public function startIgnoring(int $findingId): void
    {
        $this->ignoringId = $this->site->findings()->findOrFail($findingId)->id;
        $this->ignoreReason = '';
    }

    public function ignore(): void
    {
        Gate::authorize('update', $this->site);

        $this->validate(['ignoreReason' => ['required', 'string', 'max:255']]);

        $finding = $this->site->findings()->findOrFail($this->ignoringId);
        $finding->update(['status' => FindingStatus::Ignored, 'ignored_reason' => $this->ignoreReason]);

        $this->recalculateScore();
        $this->reset('ignoringId', 'ignoreReason');
        $this->dispatch('close-modal', name: 'ignore-finding');

        Flux::toast(text: __('Finding ignored. It no longer counts toward the score.'));
    }

    public function reopen(int $findingId): void
    {
        Gate::authorize('update', $this->site);

        $this->site->findings()->findOrFail($findingId)->update([
            'status' => FindingStatus::Open,
            'ignored_reason' => null,
            'resolved_at' => null,
        ]);

        $this->recalculateScore();
    }

    public function explain(int $findingId, RemediationAdvisor $advisor): void
    {
        Gate::authorize('view', $this->site);

        $finding = $this->site->findings()->findOrFail($findingId);

        try {
            $this->explanations[$finding->id] = $advisor->explain($finding)->body;
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: __('The AI explanation could not be generated right now.'));
        }
    }

    public function issueToken(): void
    {
        Gate::authorize('manageAgent', $this->site);

        $this->newToken = $this->site->issueAgentToken();
    }

    public function revokeToken(): void
    {
        Gate::authorize('manageAgent', $this->site);

        $this->site->revokeAgentToken();
        $this->newToken = null;

        Flux::toast(text: __('Agent token revoked.'));
    }

    public function saveSettings(): void
    {
        Gate::authorize('update', $this->site);

        $this->validate(['scanFrequency' => ['required', Rule::enum(ScanFrequency::class)]]);

        $this->site->update(['scan_frequency' => $this->scanFrequency]);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    public function deleteSite(): void
    {
        Gate::authorize('delete', $this->site);

        $host = $this->site->host;
        $this->site->delete();

        Flux::toast(variant: 'success', text: __(':host was removed.', ['host' => $host]));

        $this->redirectRoute('sites.index', navigate: true);
    }

    private function recalculateScore(): void
    {
        $result = Scoring::calculate($this->site->openFindings()->get()->map(fn (Finding $finding) => $finding->severity));
        $this->site->forceFill($result)->save();
        unset($this->findings);
    }

    #[Computed]
    public function activeScan(): ?Scan
    {
        return $this->site->activeScan()?->load('checkRuns');
    }

    #[Computed]
    public function lastScan(): ?Scan
    {
        return $this->site->lastScan?->load('checkRuns');
    }

    /**
     * @return Collection<string, Collection<int, Finding>>
     */
    #[Computed]
    public function findings(): Collection
    {
        return $this->site->findings()
            ->when(! $this->showClosed, fn ($query) => $query->where('status', FindingStatus::Open))
            ->get()
            ->sortByDesc(fn (Finding $finding) => $finding->severity->rank() * 10 + ($finding->isOpen() ? 1 : 0))
            ->groupBy(fn (Finding $finding) => $finding->severity->value);
    }

    #[Computed]
    public function openCounts(): array
    {
        $counts = $this->site->openFindings()->get()->countBy(fn (Finding $finding) => $finding->severity->value);

        return collect(Severity::ordered())->mapWithKeys(fn (Severity $severity) => [$severity->value => $counts[$severity->value] ?? 0])->all();
    }

    #[Computed]
    public function scans(): Collection
    {
        return $this->site->scans()->latest('id')->limit(20)->get();
    }

    #[Computed]
    public function packageFindings(): Collection
    {
        return $this->site->packageFindings()
            ->with('advisory')
            ->where('status', FindingStatus::Open)
            ->get()
            ->sortByDesc(fn (PackageFinding $finding) => $finding->is_dev ? 0 : 1);
    }

    public function aiEnabled(): bool
    {
        return RemediationAdvisor::isEnabled();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex items-start gap-4">
            <div @class([
                'flex size-16 shrink-0 items-center justify-center rounded-xl text-3xl font-bold text-white',
                'bg-green-600' => $site->grade === 'A',
                'bg-lime-600' => $site->grade === 'B',
                'bg-yellow-500' => $site->grade === 'C',
                'bg-orange-500' => $site->grade === 'D',
                'bg-red-600' => $site->grade === 'F',
                'bg-zinc-400 dark:bg-zinc-600' => $site->grade === null,
            ]) data-test="site-grade">
                {{ $site->grade ?? '–' }}
            </div>

            <div class="min-w-0">
                <flux:heading size="xl" level="1" class="truncate">{{ $site->host }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $site->client->name }}
                    · <a href="{{ $site->url }}" target="_blank" rel="noopener noreferrer" class="underline decoration-zinc-400 underline-offset-2">{{ $site->url }}</a>
                </flux:text>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if ($site->isVerified())
                        <flux:badge color="green" size="sm" icon="shield-check">{{ __('Verified') }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm" icon="shield-exclamation">{{ __('Not verified') }}</flux:badge>
                    @endif

                    @if ($site->is_laravel)
                        <flux:badge color="red" size="sm">Laravel</flux:badge>
                    @endif

                    @if ($site->score !== null)
                        <flux:badge size="sm">{{ __('Score :score/100', ['score' => $site->score]) }}</flux:badge>
                    @endif

                    @if ($site->last_scanned_at)
                        <flux:text class="text-xs">{{ __('Last scan :time', ['time' => $site->last_scanned_at->diffForHumans()]) }}</flux:text>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            @if ($site->last_scan_id)
                <flux:button icon="printer" :href="route('sites.report', ['site' => $site])" target="_blank" data-test="report-button">
                    {{ __('Report') }}
                </flux:button>
            @endif

            <flux:button variant="primary" icon="arrow-path" wire:click="scan" :disabled="$this->activeScan !== null" data-test="scan-button">
                {{ $this->activeScan ? __('Scanning…') : __('Scan now') }}
            </flux:button>
        </div>
    </div>

    {{-- Ownership verification --}}
    @unless ($site->isVerified())
        <flux:callout variant="warning" icon="shield-exclamation" data-test="verify-panel">
            <flux:callout.heading>{{ __('Verify you own this site to enable all checks') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Until ownership is verified, Leakwatch only runs passive checks (homepage headers, cookies, TLS and DNS). Probing paths like /.env is only done on sites you prove you control. Use either method:') }}
            </flux:callout.text>

            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ __('Option 1: DNS TXT record on :host', ['host' => $site->host]) }}</flux:text>
                    <flux:input readonly copyable :value="config('leakwatch.verification.dns_prefix').$site->verification_token" />
                </div>
                <div class="space-y-2">
                    <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ __('Option 2: a file containing only the token') }}</flux:text>
                    <flux:input readonly copyable :value="rtrim($site->url, '/').config('leakwatch.verification.file_path')" />
                    <flux:input readonly copyable :value="$site->verification_token" />
                </div>
            </div>

            <div class="mt-4">
                <flux:button size="sm" variant="primary" icon="shield-check" wire:click="verify" data-test="verify-button">{{ __('Verify ownership') }}</flux:button>
            </div>
        </flux:callout>
    @endunless

    {{-- Live scan progress --}}
    @if ($this->activeScan)
        <div wire:poll.1s="tick" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="scan-progress">
            @php($progress = $this->activeScan->progress())
            <div class="flex items-center justify-between">
                <flux:heading>{{ $this->activeScan->status->value === 'queued' ? __('Starting scan…') : __('Scanning :host', ['host' => $site->host]) }}</flux:heading>
                <flux:text>{{ $progress }}%</flux:text>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div class="h-full rounded-full bg-zinc-800 transition-all duration-500 dark:bg-white" style="width: {{ $progress }}%"></div>
            </div>
            <div class="mt-4 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->activeScan->checkRuns as $run)
                    <div class="flex items-center gap-2 text-sm">
                        @if ($run->status === null)
                            <flux:icon.clock variant="mini" class="text-zinc-400" />
                        @elseif ($run->status === \App\Enums\CheckStatus::Passed)
                            <flux:icon.check-circle variant="mini" class="text-green-500" />
                        @elseif ($run->status === \App\Enums\CheckStatus::Failed)
                            <flux:icon.exclamation-triangle variant="mini" class="text-red-500" />
                        @elseif ($run->status === \App\Enums\CheckStatus::Skipped)
                            <flux:icon.minus-circle variant="mini" class="text-zinc-400" />
                        @else
                            <flux:icon.x-circle variant="mini" class="text-amber-500" />
                        @endif
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $run->checkName() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (! $this->activeScan && $site->lastScan?->status?->value === 'failed')
        <flux:callout variant="danger" icon="x-circle" :heading="__('The last scan failed')">
            <flux:callout.text>{{ $site->lastScan->error }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Severity summary --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach (\App\Enums\Severity::ordered() as $severity)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-xs uppercase tracking-wide">{{ $severity->label() }}</flux:text>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $this->openCounts[$severity->value] }}</span>
                    @if ($this->openCounts[$severity->value] > 0)
                        <flux:badge :color="$severity->color()" size="sm">{{ __('open') }}</flux:badge>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-1 border-b border-zinc-200 pb-2 dark:border-zinc-700">
        @foreach (['findings' => __('Findings'), 'checks' => __('Checks'), 'history' => __('Scan history'), 'dependencies' => __('Dependencies'), 'agent' => __('Agent'), 'settings' => __('Settings')] as $key => $label)
            <flux:button size="sm" :variant="$tab === $key ? 'filled' : 'ghost'" wire:click="$set('tab', '{{ $key }}')" data-test="tab-{{ $key }}">
                {{ $label }}
                @if ($key === 'dependencies' && $this->packageFindings->isNotEmpty())
                    <flux:badge color="red" size="sm" class="ms-1">{{ $this->packageFindings->count() }}</flux:badge>
                @endif
            </flux:button>
        @endforeach
    </div>

    {{-- Findings --}}
    @if ($tab === 'findings')
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <flux:text>
                    @if ($site->last_scan_id === null)
                        {{ __('No scan yet. Click "Scan now" to check this site.') }}
                    @else
                        {{ trans_choice(':count open finding|:count open findings', array_sum($this->openCounts), ['count' => array_sum($this->openCounts)]) }}
                    @endif
                </flux:text>
                <flux:switch wire:model.live="showClosed" :label="__('Show resolved & ignored')" />
            </div>

            @if ($site->last_scan_id && $this->findings->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="no-findings">
                    <flux:icon.shield-check class="mx-auto size-10 text-green-500" />
                    <flux:heading class="mt-3">{{ __('Nothing open. Nice work.') }}</flux:heading>
                    @unless ($site->isVerified())
                        <flux:text class="mt-1">{{ __('Verify ownership to run the deeper Laravel checks.') }}</flux:text>
                    @endunless
                </div>
            @endif

            @foreach (\App\Enums\Severity::ordered() as $severity)
                @foreach ($this->findings->get($severity->value, collect()) as $finding)
                    <div wire:key="finding-{{ $finding->id }}" @class([
                        'rounded-xl border p-5',
                        'border-zinc-200 dark:border-zinc-700' => $finding->isOpen(),
                        'border-dashed border-zinc-200 opacity-70 dark:border-zinc-700' => ! $finding->isOpen(),
                    ]) data-test="finding">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge :color="$finding->severity->color()" size="sm">{{ $finding->severity->label() }}</flux:badge>
                                    @unless ($finding->isOpen())
                                        <flux:badge :color="$finding->status->color()" size="sm">{{ $finding->status->label() }}</flux:badge>
                                    @endunless
                                    <flux:heading>{{ $finding->title }}</flux:heading>
                                </div>
                                <flux:text>{{ $finding->summary }}</flux:text>
                            </div>

                            <div class="flex shrink-0 gap-1">
                                @if ($finding->isOpen())
                                    @if ($this->aiEnabled() && ! isset($explanations[$finding->id]))
                                        <flux:button size="sm" variant="ghost" icon="sparkles" wire:click="explain({{ $finding->id }})" wire:target="explain({{ $finding->id }})">{{ __('Explain with AI') }}</flux:button>
                                    @endif
                                    <flux:modal.trigger name="ignore-finding">
                                        <flux:button size="sm" variant="ghost" icon="eye-slash" wire:click="startIgnoring({{ $finding->id }})">{{ __('Ignore') }}</flux:button>
                                    </flux:modal.trigger>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="reopen({{ $finding->id }})">{{ __('Reopen') }}</flux:button>
                                @endif
                            </div>
                        </div>

                        @if (! empty($finding->evidence))
                            <dl class="mt-4 grid gap-x-4 gap-y-1 rounded-lg bg-zinc-50 p-3 text-sm sm:grid-cols-[max-content_1fr] dark:bg-zinc-900">
                                @foreach ($finding->evidence as $key => $value)
                                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ str_replace('_', ' ', $key) }}</dt>
                                    <dd class="break-all font-mono text-xs text-zinc-800 dark:text-zinc-200">
                                        {{ is_array($value) ? (empty($value) ? '—' : implode(', ', $value)) : (is_bool($value) ? ($value ? 'yes' : 'no') : $value) }}
                                    </dd>
                                @endforeach
                            </dl>
                        @endif

                        <details class="group mt-4">
                            <summary class="cursor-pointer text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('How to fix it') }}</summary>
                            <div class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $finding->remediation }}</div>
                        </details>

                        @if (isset($explanations[$finding->id]))
                            <div class="mt-4 rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm dark:border-violet-900 dark:bg-violet-950" data-test="ai-explanation">
                                <div class="mb-2 flex items-center gap-1 text-xs font-medium text-violet-700 dark:text-violet-300">
                                    <flux:icon.sparkles variant="micro" /> {{ __('AI-written explanation – review before acting') }}
                                </div>
                                <div class="whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $explanations[$finding->id] }}</div>
                            </div>
                        @endif

                        <flux:text class="mt-3 text-xs">
                            {{ __('First seen :date', ['date' => $finding->created_at->toFormattedDateString()]) }}
                            @if ($finding->resolved_at) · {{ __('Resolved :date', ['date' => $finding->resolved_at->toFormattedDateString()]) }} @endif
                            @if ($finding->ignored_reason) · {{ __('Ignored: :reason', ['reason' => $finding->ignored_reason]) }} @endif
                        </flux:text>
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif

    {{-- Checks of the last scan --}}
    @if ($tab === 'checks')
        @if ($this->lastScan)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Check') }}</flux:table.column>
                    <flux:table.column>{{ __('Result') }}</flux:table.column>
                    <flux:table.column>{{ __('Note') }}</flux:table.column>
                    <flux:table.column>{{ __('Time') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->lastScan->checkRuns as $run)
                        <flux:table.row :key="$run->id">
                            <flux:table.cell class="font-medium">{{ $run->checkName() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($run->status)
                                    <flux:badge :color="$run->status->color()" size="sm">{{ $run->status->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-w-md whitespace-normal text-sm">{{ $run->error ?? ($run->result['note'] ?? '') }}</flux:table.cell>
                            <flux:table.cell>{{ $run->duration_ms !== null ? $run->duration_ms.' ms' : '' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:text>{{ __('Run a scan to see each check.') }}</flux:text>
        @endif
    @endif

    {{-- History --}}
    @if ($tab === 'history')
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Trigger') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Grade') }}</flux:table.column>
                <flux:table.column>{{ __('New') }}</flux:table.column>
                <flux:table.column>{{ __('Resolved') }}</flux:table.column>
                <flux:table.column>{{ __('Duration') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->scans as $scan)
                    <flux:table.row :key="$scan->id">
                        <flux:table.cell>{{ $scan->created_at->toDayDateTimeString() }}</flux:table.cell>
                        <flux:table.cell>{{ $scan->trigger->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$scan->status->color()" size="sm">{{ $scan->status->label() }}</flux:badge>
                            @if ($scan->error)
                                <div class="mt-1 max-w-md whitespace-normal text-xs text-red-600 dark:text-red-400">{{ $scan->error }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($scan->grade)
                                <flux:badge :color="\App\Scanning\Scoring::gradeColor($scan->grade)" size="sm">{{ $scan->grade }} · {{ $scan->score }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $scan->new_findings_count }}</flux:table.cell>
                        <flux:table.cell>{{ $scan->resolved_findings_count }}</flux:table.cell>
                        <flux:table.cell>{{ $scan->durationForHumans() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">{{ __('No scans yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Dependencies --}}
    @if ($tab === 'dependencies')
        @php($snapshot = $site->latestSnapshot)
        @if ($snapshot === null)
            <div class="rounded-xl border border-dashed border-zinc-300 p-8 dark:border-zinc-600">
                <flux:heading>{{ __('No package data yet') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Install the Leakwatch agent in this Laravel app (see the Agent tab). It reports the installed Composer packages once a day, and Leakwatch alerts you when any of them gets a security advisory.') }}</flux:text>
                <flux:button size="sm" class="mt-4" icon="key" wire:click="$set('tab', 'agent')">{{ __('Set up the agent') }}</flux:button>
            </div>
        @else
            <flux:text>
                {{ __(':count packages reported :time', ['count' => $snapshot->package_count, 'time' => $snapshot->reported_at->diffForHumans()]) }}
                @if ($snapshot->laravel_version) · Laravel {{ $snapshot->laravel_version }} @endif
                @if ($snapshot->php_version) · PHP {{ $snapshot->php_version }} @endif
                @if (! $snapshot->analyzed_at) · <span class="text-amber-600">{{ __('analysis pending') }}</span> @endif
            </flux:text>

            @if ($this->packageFindings->isEmpty())
                <flux:callout variant="success" icon="shield-check" :heading="__('No known vulnerabilities in the installed packages.')" />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Package') }}</flux:table.column>
                        <flux:table.column>{{ __('Installed') }}</flux:table.column>
                        <flux:table.column>{{ __('Advisory') }}</flux:table.column>
                        <flux:table.column>{{ __('Severity') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->packageFindings as $packageFinding)
                            <flux:table.row :key="$packageFinding->id">
                                <flux:table.cell class="font-mono text-xs">
                                    {{ $packageFinding->package_name }}
                                    @if ($packageFinding->is_dev) <flux:badge size="sm">dev</flux:badge> @endif
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $packageFinding->installed_version }}</flux:table.cell>
                                <flux:table.cell class="max-w-md whitespace-normal">
                                    @if ($packageFinding->advisory->link)
                                        <a href="{{ $packageFinding->advisory->link }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $packageFinding->advisory->title }}</a>
                                    @else
                                        {{ $packageFinding->advisory->title }}
                                    @endif
                                    @if ($packageFinding->advisory->cve) <span class="text-xs text-zinc-500">({{ $packageFinding->advisory->cve }})</span> @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$packageFinding->advisory->severityColor()" size="sm">{{ ucfirst($packageFinding->advisory->severity ?? 'unknown') }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                <flux:text class="text-sm">{{ __('Fix: run composer update for the listed packages (e.g. composer update vendor/package --with-dependencies), deploy, and the agent\'s next report will mark them resolved.') }}</flux:text>
            @endif
        @endif
    @endif

    {{-- Agent --}}
    @if ($tab === 'agent')
        <div class="max-w-3xl space-y-4">
            <flux:heading>{{ __('Leakwatch agent') }}</flux:heading>
            <flux:text>{{ __('A tiny Composer package you install in the monitored Laravel app. Once a day it sends the list of installed packages (names and versions only - never code, config or secrets) so Leakwatch can match them against Packagist security advisories.') }}</flux:text>

            @if ($site->agent_last_seen_at)
                <flux:badge color="green" icon="check-circle">{{ __('Last report :time', ['time' => $site->agent_last_seen_at->diffForHumans()]) }}</flux:badge>
            @endif

            @can('manageAgent', $site)
                @if ($newToken)
                    <flux:callout variant="success" icon="key" :heading="__('Copy this token now - it will not be shown again.')">
                        <flux:input readonly copyable :value="$newToken" data-test="agent-token" />
                    </flux:callout>
                @endif

                <div class="flex gap-2">
                    <flux:button size="sm" icon="key" wire:click="issueToken" data-test="issue-token">
                        {{ $site->agent_token_hash ? __('Regenerate token') : __('Create token') }}
                    </flux:button>
                    @if ($site->agent_token_hash)
                        <flux:button size="sm" variant="danger" wire:click="revokeToken" wire:confirm="{{ __('Revoke the token? The agent will stop reporting.') }}">{{ __('Revoke') }}</flux:button>
                        <flux:text class="self-center text-sm">{{ __('Current token ends in …:last', ['last' => $site->agent_token_last_four]) }}</flux:text>
                    @endif
                </div>
            @else
                <flux:text class="text-sm">{{ __('Only team owners and admins can create agent tokens.') }}</flux:text>
            @endcan

            <div class="rounded-xl bg-zinc-900 p-4 font-mono text-xs leading-relaxed text-zinc-100">
                <div class="text-zinc-400"># 1. In the monitored app's composer.json, add the package (path repo shown for local demos)</div>
                <div>composer config repositories.leakwatch path {{ base_path('packages/leakwatch/agent') }}</div>
                <div>composer require leakwatch/agent:@dev</div>
                <div class="mt-2 text-zinc-400"># 2. Add to that app's .env</div>
                <div>LEAKWATCH_ENDPOINT={{ url('/api/v1/agent/report') }}</div>
                <div>LEAKWATCH_TOKEN={{ $newToken ?? 'lw_your_token_here' }}</div>
                <div class="mt-2 text-zinc-400"># 3. Send the first report (afterwards it runs daily via the scheduler)</div>
                <div>php artisan leakwatch:report</div>
            </div>
        </div>
    @endif

    {{-- Settings --}}
    @if ($tab === 'settings')
        <div class="max-w-xl space-y-6">
            <form wire:submit="saveSettings" class="space-y-4">
                <flux:select wire:model="scanFrequency" :label="__('Automatic scans')">
                    @foreach (\App\Enums\ScanFrequency::cases() as $frequency)
                        <flux:select.option :value="$frequency->value">{{ $frequency->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:text class="text-sm">{{ __('Scheduled scans only run on verified sites.') }}</flux:text>
                <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
            </form>

            @can('delete', $site)
                <flux:separator />
                <div class="space-y-2">
                    <flux:heading>{{ __('Remove site') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('Deletes the site, its scans, findings and package data.') }}</flux:text>
                    <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteSite" wire:confirm="{{ __('Remove :host and all its data?', ['host' => $site->host]) }}" data-test="delete-site">{{ __('Remove site') }}</flux:button>
                </div>
            @endcan
        </div>
    @endif

    {{-- Ignore modal --}}
    <flux:modal name="ignore-finding" :show="$errors->has('ignoreReason')" focusable class="max-w-lg">
        <form wire:submit="ignore" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Ignore this finding?') }}</flux:heading>
                <flux:subheading>{{ __('Ignored findings stop affecting the score and alerts. Record why, so your team knows later.') }}</flux:subheading>
            </div>
            <flux:input wire:model="ignoreReason" :label="__('Reason')" :placeholder="__('e.g. Accepted risk: internal staging site')" required />
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Ignore finding') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
