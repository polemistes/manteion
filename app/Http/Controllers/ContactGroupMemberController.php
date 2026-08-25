<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactGroupMemberRequest;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ContactGroupMemberController extends Controller
{
    public function store(StoreContactGroupMemberRequest $request, ContactGroup $contactGroup): RedirectResponse
    {
        $contactGroup->members()->syncWithoutDetaching([$request->integer('member_user_id')]);

        return back();
    }

    public function destroy(ContactGroup $contactGroup, User $member): RedirectResponse
    {
        Gate::authorize('update', $contactGroup);

        $contactGroup->members()->detach($member);

        return back();
    }
}
