<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactGroupRequest;
use App\Http\Requests\UpdateContactGroupRequest;
use App\Models\ContactGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ContactGroupController extends Controller
{
    public function store(StoreContactGroupRequest $request): RedirectResponse
    {
        $request->user()->contactGroups()->create($request->validated());

        return back();
    }

    public function update(UpdateContactGroupRequest $request, ContactGroup $contactGroup): RedirectResponse
    {
        $contactGroup->update($request->validated());

        return back();
    }

    public function destroy(ContactGroup $contactGroup): RedirectResponse
    {
        Gate::authorize('delete', $contactGroup);

        $contactGroup->delete();

        return back();
    }
}
