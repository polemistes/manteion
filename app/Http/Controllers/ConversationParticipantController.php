<?php

namespace App\Http\Controllers;

use App\Events\BadgesUpdated;
use App\Events\ParticipantsUpdated;
use App\Http\Requests\StoreConversationParticipantRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationParticipantController extends Controller
{
    /**
     * The new member sees the history, so the conversation may count as
     * unread for them straight away.
     */
    public function store(StoreConversationParticipantRequest $request, Conversation $conversation): RedirectResponse
    {
        $participant = ConversationParticipant::query()->firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $request->integer('user_id'),
        ]);

        if ($participant->wasRecentlyCreated) {
            ParticipantsUpdated::dispatch($conversation->id);
            BadgesUpdated::dispatch($participant->user_id);
        }

        return back();
    }

    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('leave', $conversation);

        $user = $request->user();

        $conversation->participantFor($user)?->delete();
        $conversation->setViewing($user, false);

        ParticipantsUpdated::dispatch($conversation->id);
        BadgesUpdated::dispatch($user->id);

        return redirect()->route('messages.index');
    }
}
