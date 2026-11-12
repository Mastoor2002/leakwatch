<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;

/**
 * Sites belong to a team. Members can view, scan and verify them; owners and
 * admins can delete them and manage agent tokens.
 */
class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, Site $site): bool
    {
        return $this->isMember($user, $site->team);
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null && $user->belongsToTeam($user->currentTeam);
    }

    public function update(User $user, Site $site): bool
    {
        return $this->isMember($user, $site->team);
    }

    public function scan(User $user, Site $site): bool
    {
        return $this->isMember($user, $site->team);
    }

    public function manageAgent(User $user, Site $site): bool
    {
        return $this->isAdmin($user, $site->team);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->isAdmin($user, $site->team);
    }

    private function isMember(User $user, ?Team $team): bool
    {
        return $team !== null && $user->belongsToTeam($team);
    }

    private function isAdmin(User $user, ?Team $team): bool
    {
        return $this->isMember($user, $team)
            && ($user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false);
    }
}
