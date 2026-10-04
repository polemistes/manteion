<?php

namespace App\Http\Controllers;

use App\Events\MessageUpdated;
use App\Http\Requests\StoreReactionRequest;
use App\Models\Message;
use App\Models\Reaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ReactionController extends Controller
{
    /**
     * A user has one reaction per item. Sending the emoji they already
     * used removes it; sending another replaces it. A JSON request gets the
     * item's reactions back as they now are; others in an open conversation
     * get the message's new reactions over its channel.
     */
    public function store(StoreReactionRequest $request): JsonResponse|RedirectResponse
    {
        $reactable = $request->reactable();
        $emoji = $request->validated('emoji');

        $existing = Reaction::query()
            ->where('user_id', $request->user()->id)
            ->where('reactable_type', $reactable->getMorphClass())
            ->where('reactable_id', $reactable->id)
            ->first();

        if ($existing === null) {
            Reaction::query()->create([
                'user_id' => $request->user()->id,
                'reactable_type' => $reactable->getMorphClass(),
                'reactable_id' => $reactable->id,
                'emoji' => $emoji,
            ]);
        } elseif ($existing->emoji === $emoji) {
            $existing->delete();
        } else {
            $existing->update(['emoji' => $emoji]);
        }

        if ($reactable instanceof Message) {
            broadcast(new MessageUpdated($reactable))->toOthers();
        }

        if ($request->wantsJson()) {
            return response()->json(['reactions' => Reaction::summarize($reactable->reactions, $request->user())]);
        }

        return back();
    }
}
