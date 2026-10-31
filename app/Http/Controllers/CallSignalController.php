<?php

namespace App\Http\Controllers;

use App\Enums\CallPushKind;
use App\Enums\CallSignalType;
use App\Events\CallSignal;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Notifications\CallNotification;
use App\Services\CallRings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallSignalController extends Controller
{
    /**
     * Passes one call set-up message (ring, offer, answer, candidates,
     * hangup…) to the other participant's browser, and to the sender's
     * other tabs, over their own channels. The server keeps nothing but
     * the ring (CallRings, for pages that open late) and never sees the
     * media: the call itself runs directly between the two browsers.
     */
    public function store(Request $request, Conversation $conversation): Response
    {
        abort_unless(config('calls.enabled'), 404);

        Gate::authorize('call', $conversation);

        $validated = $request->validate([
            'call_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(CallSignalType::class)],
            'from_tab' => ['required', 'string', 'max:64'],
            'to_tab' => ['nullable', 'string', 'max:64'],
            'data' => ['nullable', 'array'],
        ]);

        $sender = $request->user();
        $type = CallSignalType::from($validated['type']);

        broadcast(new CallSignal(
            conversation: $conversation,
            sender: $sender,
            type: $type,
            callId: $validated['call_id'],
            fromTab: $validated['from_tab'],
            toTab: $validated['to_tab'] ?? null,
            data: $validated['data'] ?? [],
        ));

        $this->trackRing($type, $conversation, $sender, $validated['call_id'], $validated['from_tab']);

        return response()->noContent();
    }

    /**
     * The ring the user is being offered right now, for a page that opens
     * while someone is calling (from the push notification, say) and so
     * missed the broadcast.
     */
    public function ringing(Request $request): JsonResponse
    {
        return response()->json([
            'ring' => config('calls.enabled') ? CallRings::pending($request->user()->id) : null,
        ]);
    }

    /**
     * Keeps the pending ring and the callee's devices in step with the
     * call: a ring is remembered and pushed; the caller giving up turns it
     * into a missed call; the callee taking or refusing it (or being busy)
     * takes the ring down on their other devices.
     */
    private function trackRing(CallSignalType $type, Conversation $conversation, User $sender, string $callId, string $fromTab): void
    {
        if ($type === CallSignalType::Ring || $type === CallSignalType::Cancel) {
            $conversation->loadMissing('participants.user');

            $conversation->participants
                ->reject(fn (ConversationParticipant $participant) => $participant->user_id === $sender->id)
                ->each(function (ConversationParticipant $callee) use ($type, $conversation, $sender, $callId, $fromTab) {
                    if ($type === CallSignalType::Ring) {
                        $ring = CallRings::ring($callee->user_id, $conversation, $sender, $callId, $fromTab);
                        $this->push(CallPushKind::Ring, $callee->user, $conversation, $sender, $ring);
                    } elseif (($ring = CallRings::settle($callee->user_id, $callId)) !== null) {
                        $this->push(CallPushKind::Missed, $callee->user, $conversation, $sender, $ring);
                    }
                });

            return;
        }

        if (in_array($type, [CallSignalType::Accept, CallSignalType::Decline, CallSignalType::Busy], true)) {
            $ring = CallRings::settle($sender->id, $callId);

            if ($ring !== null) {
                $caller = User::query()->find($ring['from']['id']);

                if ($caller !== null) {
                    $this->push(CallPushKind::Over, $sender, $conversation, $caller, $ring);
                }
            }
        }
    }

    /**
     * @param  array{call_id: string, started_at: int, expires_at: int}  $ring
     */
    private function push(CallPushKind $kind, User $callee, Conversation $conversation, User $caller, array $ring): void
    {
        $callee->notify(CallNotification::about($kind, $conversation, $caller, $ring)->locale($callee->locale ?? 'en'));
    }
}
