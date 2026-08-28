<?php

namespace App\Http\Controllers;

use App\Enums\ConversationType;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->with(['participants.user', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->get()
            ->map(function (Conversation $conversation) use ($user) {
                $myParticipant = $conversation->participantFor($user);
                $lastMessage = $conversation->messages->first();
                $others = $conversation->participants
                    ->reject(fn (ConversationParticipant $participant) => $participant->user_id === $user->id)
                    ->map(fn (ConversationParticipant $participant) => $participant->user);

                $unread = $lastMessage !== null
                    && $lastMessage->user_id !== $user->id
                    && ($myParticipant?->last_read_at === null || $lastMessage->created_at->gt($myParticipant->last_read_at));

                $activityAt = $lastMessage === null ? $conversation->created_at : $lastMessage->created_at;

                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type->value,
                    'title' => $this->title($conversation, $others),
                    'avatar_url' => $conversation->type === ConversationType::Direct ? $others->first()?->avatar_url : null,
                    'last_message' => $lastMessage === null ? null : [
                        'body' => Str::limit($lastMessage->body, 80),
                        'is_mine' => $lastMessage->user_id === $user->id,
                    ],
                    'unread' => $unread,
                    'activity_at' => $activityAt->toIso8601String(),
                ];
            })
            ->sortByDesc('activity_at')
            ->values();

        return Inertia::render('messages/Index', [
            'conversations' => $conversations,
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
            ? $this->findOrCreateDirectConversation($creator, $targetIds->first())
            : $this->createGroupConversation($creator, $targetIds, $request->string('name')->value() ?: null);

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $conversation->load(['participants.user', 'messages' => fn ($query) => $query->with('sender')->latest()->limit(100)]);

        $conversation->participantFor($user)?->update(['last_read_at' => now()]);

        $others = $conversation->participants
            ->reject(fn (ConversationParticipant $participant) => $participant->user_id === $user->id)
            ->map(fn (ConversationParticipant $participant) => $participant->user);

        $alreadyInIds = $conversation->participants->pluck('user_id');

        return Inertia::render('messages/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type->value,
                'title' => $this->title($conversation, $others),
            ],
            'participants' => $conversation->participants->map(fn (ConversationParticipant $participant) => [
                'id' => $participant->user->id,
                'name' => $participant->user->name,
                'username' => $participant->user->username,
                'avatar_url' => $participant->user->avatar_url,
                'is_me' => $participant->user_id === $user->id,
            ])->values(),
            'messages' => $conversation->messages->sortBy('created_at')->values()->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->is_deleted ? null : $message->body,
                'is_deleted' => $message->is_deleted,
                'created_at' => $message->created_at->toIso8601String(),
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'username' => $message->sender->username,
                    'avatar_url' => $message->sender->avatar_url,
                ],
                'is_mine' => $message->user_id === $user->id,
            ])->values(),
            'canAddParticipant' => Gate::allows('addParticipant', $conversation),
            'canLeave' => Gate::allows('leave', $conversation),
            'addCandidates' => $user->friends()
                ->reject(fn (User $friend) => $alreadyInIds->contains($friend->id))
                ->map(fn (User $friend) => ['id' => $friend->id, 'name' => $friend->name, 'username' => $friend->username, 'avatar_url' => $friend->avatar_url])
                ->values(),
        ]);
    }

    private function findOrCreateDirectConversation(User $creator, int $targetId): Conversation
    {
        $existing = Conversation::query()
            ->where('type', ConversationType::Direct)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $creator->id))
            ->whereHas('participants', fn ($query) => $query->where('user_id', $targetId))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Direct,
            'creator_id' => $creator->id,
        ]);

        $conversation->participants()->createMany([
            ['user_id' => $creator->id],
            ['user_id' => $targetId],
        ]);

        return $conversation;
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

    /**
     * @param  Collection<int, User>  $others
     */
    private function title(Conversation $conversation, Collection $others): string
    {
        if ($conversation->type === ConversationType::Direct) {
            return $others->first()->name;
        }

        return $conversation->name ?? $others->pluck('name')->join(', ');
    }
}
