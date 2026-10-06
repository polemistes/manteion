<?php

namespace App\Policies;

use App\Models\Organizer;
use App\Models\User;

class OrganizerPolicy
{
    /**
     * Organizers are public: any member may see the page.
     */
    public function view(User $user, Organizer $organizer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Acting for the organizer: hosting its events, publishing its leaflets.
     */
    public function act(User $user, Organizer $organizer): bool
    {
        return $organizer->hasSpokesperson($user);
    }

    /**
     * The organizer itself (name, description) and its spokespersons are
     * the main spokesperson's.
     */
    public function update(User $user, Organizer $organizer): bool
    {
        return $organizer->isMainSpokesperson($user);
    }

    public function delete(User $user, Organizer $organizer): bool
    {
        return $organizer->isMainSpokesperson($user) || $user->isStaff();
    }
}
