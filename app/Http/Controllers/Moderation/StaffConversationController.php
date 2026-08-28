<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffConversationController extends Controller
{
    /**
     * Lets a moderator or administrator message the reporter or the
     * reported-on user directly from the moderation queue.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $conversation = Conversation::findOrCreateDirect($request->user(), $user);

        return redirect()->route('messages.show', $conversation);
    }
}
