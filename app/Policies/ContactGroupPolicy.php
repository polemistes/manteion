<?php

namespace App\Policies;

use App\Models\ContactGroup;
use App\Models\User;

class ContactGroupPolicy
{
    public function view(User $user, ContactGroup $contactGroup): bool
    {
        return $user->is($contactGroup->owner);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContactGroup $contactGroup): bool
    {
        return $user->is($contactGroup->owner);
    }

    public function delete(User $user, ContactGroup $contactGroup): bool
    {
        return $user->is($contactGroup->owner);
    }
}
