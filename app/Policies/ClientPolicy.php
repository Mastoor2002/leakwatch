<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Team;
use App\Models\User;

/**
 * Any team member can view and edit clients; only owners and admins can delete.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $this->isMember($user, $client->team);
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null && $user->belongsToTeam($user->currentTeam);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->isMember($user, $client->team);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->isMember($user, $client->team)
            && ($user->teamRole($client->team)?->isAtLeast(TeamRole::Admin) ?? false);
    }

    private function isMember(User $user, ?Team $team): bool
    {
        return $team !== null && $user->belongsToTeam($team);
    }
}
