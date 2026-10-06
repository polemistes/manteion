<?php

namespace App\Http\Controllers;

use App\Enums\ConversationType;
use App\Events\BadgesUpdated;
use App\Events\ConversationRead;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('messages/Index', [
            'conversations' => $this->conversationList($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Conversation::class);

        return Inertia::render('messages/Create', [
            'friends' => $request->user()->friends()
                ->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])
                ->values(),
        ]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $creator = $request->user();
        $targetIds = collect($request->array('user_ids'))->map(fn ($id) => (int) $id)->unique()->values();

        $conversation = $targetIds->count() === 1
            ? Conversation::findOrCreateDirect($creator, User::findOrFail($targetIds->first()))
            : $this->createGroupConversation($creator, $targetIds, $request->string('name')->value() ?: null);

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * The thread, with the conversation list beside it for the two-pane
     * layout. Opening it marks it read, but only when the user really looked:
     * not on a partial reload (a background tab refreshing a prop), a hover
     * prefetch, or an administrator browsing as them.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $conversation->loadMissing('participants.user');
        $participant = $conversation->participantFor($user);

        $seenByUser = $request->header(Header::PARTIAL_COMPONENT) !== 'messages/Show'
            && ! $request->prefetch()
            && ! $request->session()->has('impersonator_id');

        if ($participant !== null && $seenByUser && $participant->hasUnreadMessages()) {
            $participant->markRead();

            BadgesUpdated::dispatch($user->id);

            if ($user->share_read_receipts) {
                ConversationRead::dispatch($participant);
            }
        }

        return Inertia::render('messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type->value,
                'title' => $conversation->titleFor($user),
                'avatar_url' => $this->avatarUrl($conversation, $user),
                'muted' => $participant?->isMuted() ?? false,
            ],
            'participants' => $conversation->participants->sortBy('id')->values()->map(fn (ConversationParticipant $member) => [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'username' => $member->user->username,
                'avatar_url' => $member->user->avatar_url,
                'is_me' => $member->user_id === $user->id,
                // For "Seen" under the viewer's last message: only from those
                // who show when they have read, and only to a viewer who does.
                'last_read_at' => $user->share_read_receipts && $member->user->share_read_receipts ? $member->last_read_at?->toIso8601String() : null,
            ]),
            'messages' => fn () => $conversation->messages()
                ->with(['sender', 'reactions', 'seenBy'])
                ->latest('id')
                ->limit(ConversationMessageController::PAGE_SIZE)
                ->get()
                ->sortBy('id')
                ->map(fn (Message $message) => MessageSummary::for($message, $user))
                ->values()
                ->all(),
            // Older messages exist when there are more than one page's worth.
            'hasOlder' => fn () => $conversation->messages()->skip(ConversationMessageController::PAGE_SIZE)->take(1)->exists(),
            'canAddParticipant' => Gate::allows('addParticipant', $conversation),
            'canLeave' => Gate::allows('leave', $conversation),
            'addCandidates' => fn () => $user->friends()
                ->reject(fn (User $friend) => $conversation->participants->contains('user_id', $friend->id))
                ->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])
                ->values()
                ->all(),
            'conversations' => fn () => $this->conversationList($user),
        ]);
    }

    /**
     * The user's conversations as the list shows them, most recent activity
     * first. A fixed handful of queries however many conversations there
     * are: the newest message and the unread count come with the main query
     * (see Conversation::scopeListedFor), participants and senders are eager
     * loaded.
     *
     * @return array<int, array<string, mixed>>
     */
    private function conversationList(User $user): array
    {
        return Conversation::query()
            ->listedFor($user)
            ->with('participants.user:id,name,avatar_path')
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => [
                ($conversation->latestMessage->created_at ?? $conversation->created_at)->getTimestamp(),
                $conversation->id,
            ])
            ->map(fn (Conversation $conversation) => $this->listItem($conversation, $user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Conversation $conversation, User $user): array
    {
        $lastMessage = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'title' => $conversation->titleFor($user),
            'avatar_url' => $this->avatarUrl($conversation, $user),
            // A few faces for a group's avatar stack.
            'avatars' => $conversation->otherParticipants($user)
                ->take(3)
                ->map(fn (User $other) => ['id' => $other->id, 'name' => $other->name, 'avatar_url' => $other->avatar_url])
                ->values()
                ->all(),
            'last_message' => $lastMessage === null ? null : [
                'body' => MessageSummary::preview($lastMessage),
                'is_mine' => $lastMessage->user_id === $user->id,
                'sender_name' => $lastMessage->sender->name,
                'is_deleted' => $lastMessage->is_deleted,
                'created_at' => $lastMessage->created_at->toIso8601String(),
            ],
            'unread' => $conversation->unread_count > 0,
            'unread_count' => $conversation->unread_count,
            'muted' => $conversation->participantFor($user)?->isMuted() ?? false,
            'activity_at' => ($lastMessage->created_at ?? $conversation->created_at)->toIso8601String(),
        ];
    }

    /**
     * A direct conversation shows the other person's picture; a group has
     * none of its own.
     */
    private function avatarUrl(Conversation $conversation, User $user): ?string
    {
        return $conversation->type === ConversationType::Direct
            ? $conversation->otherParticipants($user)->first()?->avatar_url
            : null;
    }

    /**
     * @param  Collection<int, int>  $targetIds
     */
    private function createGroupConversation(User $creator, Collection $targetIds, ?string $name): Conversation
    {
        $conversation = Conversation::create([
            'type' => ConversationType::Group,
            'name' => $name,
            'creator_id' => $creator->id,
        ]);

        $participantIds = $targetIds->push($creator->id)->unique();

        $conversation->participants()->createMany(
            $participantIds->map(fn (int $id) => ['user_id' => $id])->all()
        );

        return $conversation;
    }
}
