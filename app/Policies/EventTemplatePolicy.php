<?php

namespace App\Policies;

use App\Models\EventTemplate;
use App\Models\User;

class EventTemplatePolicy
{
    /**
     * Seeing a template is using it: starting an event from it, and
     * seeing its cover.
     */
    public function view(User $user, EventTemplate $template): bool
    {
        return $template->isUsableBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EventTemplate $template): bool
    {
        return $template->isUsableBy($user);
    }

    public function delete(User $user, EventTemplate $template): bool
    {
        return $template->isUsableBy($user);
    }
}
