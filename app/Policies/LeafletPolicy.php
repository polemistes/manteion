<?php

namespace App\Policies;

use App\Models\Leaflet;
use App\Models\User;

class LeafletPolicy
{
    public function view(User $user, Leaflet $leaflet): bool
    {
        return $leaflet->isVisibleTo($user) || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Leaflet $leaflet): bool
    {
        return $leaflet->isOwnedBy($user);
    }

    public function delete(User $user, Leaflet $leaflet): bool
    {
        return $leaflet->isOwnedBy($user) || $user->isStaff();
    }
}
