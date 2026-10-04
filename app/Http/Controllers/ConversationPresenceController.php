<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationPresenceController extends Controller
{
    /**
     * A heartbeat from the open thread: while it keeps coming (every ~25
     * seconds while the thread is visible), new messages in it are not
     * pushed to the user's devices. `viewing: false` ends it at once when
     * the thread is hidden or left.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();

        abort_unless($conversation->isVisibleTo($user), 403);

        $request->validate(['viewing' => ['required', 'boolean']]);

        $conversation->setViewing($user, $request->boolean('viewing'));

        return response()->noContent();
    }
}
