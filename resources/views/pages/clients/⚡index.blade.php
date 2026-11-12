<?php

use App\Enums\FindingStatus;
use App\Models\Client;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Clients')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $contactEmail = '';

    public string $notes = '';

    public function create(): void
    {
        $this->resetForm();
    }

    public function edit(int $clientId): void
    {
        $client = $this->findClient($clientId);
        Gate::authorize('update', $client);

        $this->resetErrorBag();
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->contactEmail = (string) $client->contact_email;
        $this->notes = (string) $client->notes;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'contact_email' => $validated['contactEmail'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->editingId) {
            $client = $this->findClient($this->editingId);
            Gate::authorize('update', $client);
            $client->update($attributes);
            Flux::toast(variant: 'success', text: __('Client updated.'));
        } else {
            Gate::authorize('create', Client::class);
            Auth::user()->currentTeam->clients()->create($attributes);
            Flux::toast(variant: 'success', text: __('Client added.'));
        }

        $this->dispatch('close-modal', name: 'client-form');
        $this->resetForm();
        unset($this->clients);
    }

    public function delete(int $clientId): void
    {
        $client = $this->findClient($clientId);
        Gate::authorize('delete', $client);

        $client->delete();
        unset($this->clients);

        Flux::toast(variant: 'success', text: __('Client and its sites were removed.'));
    }

    private function findClient(int $clientId): Client
    {
        return Auth::user()->currentTeam->clients()->findOrFail($clientId);
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'name', 'contactEmail', 'notes');
    }

    /**
     * @return Collection<int, Client>
     */
    #[Computed]
    public function clients(): Collection
    {
        return Auth::user()->currentTeam->clients()
            ->withCount('sites')
            ->with(['sites' => fn ($query) => $query->withCount(['findings as open_findings_count' => fn ($q) => $q->where('status', FindingStatus::Open)])])
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Clients') }}</flux:heading>
            <flux:subheading>{{ __('The businesses whose sites you look after.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="client-form">
            <flux:button variant="primary" icon="plus" size="sm" wire:click="create" data-test="add-client-button">{{ __('Add client') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->clients->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-600">
            <flux:icon.building-office class="mx-auto size-10 text-zinc-400" />
            <flux:heading class="mt-3">{{ __('No clients yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Clients group the sites you monitor, so reports and alerts make sense to the people who own them.') }}</flux:text>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->clients as $client)
                <div wire:key="client-{{ $client->id }}" class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="client-card">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <flux:heading class="truncate">{{ $client->name }}</flux:heading>
                            @if ($client->contact_email)
                                <flux:text class="truncate text-sm">{{ $client->contact_email }}</flux:text>
                            @endif
                        </div>
                        <div class="flex shrink-0">
                            <flux:modal.trigger name="client-form">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $client->id }})" />
                            </flux:modal.trigger>
                            @can('delete', $client)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $client->id }})" wire:confirm="{{ __('Delete :name and all of its sites?', ['name' => $client->name]) }}" />
                            @endcan
                        </div>
                    </div>

                    @if ($client->notes)
                        <flux:text class="mt-2 line-clamp-2 text-sm">{{ $client->notes }}</flux:text>
                    @endif

                    <div class="mt-4 space-y-1">
                        @forelse ($client->sites as $site)
                            <a href="{{ route('sites.show', ['site' => $site]) }}" wire:navigate class="flex items-center justify-between rounded-md px-2 py-1 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                <span class="truncate">{{ $site->host }}</span>
                                <span class="flex items-center gap-1">
                                    @if ($site->open_findings_count > 0)
                                        <flux:badge size="sm" color="red">{{ $site->open_findings_count }}</flux:badge>
                                    @endif
                                    <flux:badge size="sm" :color="\App\Scanning\Scoring::gradeColor($site->grade)">{{ $site->grade ?? '–' }}</flux:badge>
                                </span>
                            </a>
                        @empty
                            <flux:text class="text-sm">{{ __('No sites yet.') }}</flux:text>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="client-form" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit client') : __('Add client') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required data-test="client-name" />
            <flux:input wire:model="contactEmail" type="email" :label="__('Contact email (optional)')" />
            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="3" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" data-test="client-save">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
