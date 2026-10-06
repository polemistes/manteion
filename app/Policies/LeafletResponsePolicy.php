<?php

namespace App\Policies;

use App\Models\LeafletResponse;
use App\Models\User;

class LeafletResponsePolicy
{
    public function view(User $user, LeafletResponse $response): bool
    {
        return $response->leaflet->isVisibleTo($user) || $user->isStaff();
    }

    public function delete(User $user, LeafletResponse $response): bool
    {
        return $user->is($response->user) || $response->leaflet->isOwnedBy($user) || $user->isStaff();
    }
}
