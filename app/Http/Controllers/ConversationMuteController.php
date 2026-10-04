<?php

namespace App\Http\Controllers;

use App\Events\BadgesUpdated;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationMuteController extends Controller
{
    /**
     * A muted conversation sends no pushes or in-app alerts and leaves the
     * messages badge alone; its messages still arrive in the thread.
     */
    public function update(Request $request, Conversation $conversation): RedirectResponse|Response
    {
        $participant = $conversation->participantFor($request->user());

        abort_if($participant === null, 403);

        $request->validate(['muted' => ['required', 'boolean']]);

        $participant->update([
            'muted_at' => $request->boolean('muted') ? ($participant->muted_at ?? now()) : null,
        ]);

        BadgesUpdated::dispatch($participant->user_id);

        if ($request->wantsJson()) {
            return response()->noContent();
        }

        return back();
    }
}
