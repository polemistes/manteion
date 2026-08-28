<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateContactTagPreferenceRequest;
use App\Models\ContactTagPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ContactTagPreferenceController extends Controller
{
    public function update(UpdateContactTagPreferenceRequest $request, User $contact): RedirectResponse
    {
        $owner = $request->user();

        ContactTagPreference::query()
            ->where('user_id', $owner->id)
            ->where('contact_user_id', $contact->id)
            ->delete();

        if ($request->boolean('mute_all')) {
            ContactTagPreference::query()->create([
                'user_id' => $owner->id,
                'contact_user_id' => $contact->id,
                'tag_id' => null,
            ]);

            return back();
        }

        $rows = collect($request->array('tag_ids'))->map(fn (int $tagId) => [
            'user_id' => $owner->id,
            'contact_user_id' => $contact->id,
            'tag_id' => $tagId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($rows->isNotEmpty()) {
            ContactTagPreference::query()->insert($rows->all());
        }

        return back();
    }
}
