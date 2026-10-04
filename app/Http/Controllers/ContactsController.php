<?php

namespace App\Http\Controllers;

use App\Enums\ContactGroupType;
use App\Enums\FollowStatus;
use App\Models\ContactGroup;
use App\Models\ContactTagPreference;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Tag;
use App\Models\User;
use App\Services\MutualFriends;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContactsController extends Controller
{
    /**
     * People: friends (with how long, and the friends in common), the people
     * the user follows and who follow them, open requests both ways, the
     * user's groups, and people to find by name.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tagPreferenceRows = ContactTagPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('contact_user_id');

        $preferencesByContact = $tagPreferenceRows->map(
            fn (Collection $rows) => $rows->pluck('tag_id')->filter()->map(fn (mixed $tagId) => (int) $tagId)->values()->all()
        );

        $mutedContactIds = $tagPreferenceRows->filter(
            fn (Collection $rows) => $rows->contains(fn (ContactTagPreference $row) => $row->tag_id === null)
        )->keys();

        $friendships = Friendship::query()
            ->accepted()
            ->where(fn (Builder $query) => $query->where('requester_id', $user->id)->orWhere('addressee_id', $user->id))
            ->with(['requester', 'addressee'])
            ->get()
            ->map(fn (Friendship $friendship) => [
                'friendship' => $friendship,
                'friend' => $friendship->requester_id === $user->id ? $friendship->addressee : $friendship->requester,
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['friend']->name))
            ->values();

        $pendingFriendRequests = $user->pendingFriendRequests()->with('requester')->latest()->get();
        $followers = Follow::query()->where('followed_id', $user->id)->accepted()->with('follower')->latest()->get();
        $pendingFollowerRequests = Follow::query()->where('followed_id', $user->id)->pending()->with('follower')->latest()->get();
        $outgoingFollows = Follow::query()->where('follower_id', $user->id)->with('followed')->latest()->get();

        $findQuery = trim((string) $request->string('find'));
        $findResults = $this->findNewContacts($findQuery, $user);

        $mutualCounts = MutualFriends::counts($user, collect([
            ...$friendships->pluck('friend.id'),
            ...$pendingFriendRequests->pluck('requester_id'),
            ...$followers->pluck('follower_id'),
            ...$pendingFollowerRequests->pluck('follower_id'),
            ...array_column($findResults, 'id'),
        ]));

        $followsBack = $outgoingFollows->mapWithKeys(fn (Follow $follow) => [
            $follow->followed_id => $follow->status === FollowStatus::Accepted ? 'following' : 'pending',
        ]);

        $contactExtras = fn (User $contact) => [
            'tag_preference_ids' => $preferencesByContact->get($contact->id, []),
            'muted' => $mutedContactIds->contains($contact->id),
        ];

        return Inertia::render('contacts/Index', [
            'friends' => $friendships->map(fn (array $row) => [
                ...$this->person($row['friend']),
                ...$contactExtras($row['friend']),
                'friendship_id' => $row['friendship']->id,
                'friends_since' => $row['friendship']->updated_at?->toIso8601String(),
                'mutual_count' => $mutualCounts[$row['friend']->id] ?? null,
            ])->all(),
            'pendingFriendRequests' => $pendingFriendRequests->map(fn (Friendship $friendship) => [
                'friendship_id' => $friendship->id,
                'created_at' => $friendship->created_at?->toIso8601String(),
                'mutual_count' => $mutualCounts[$friendship->requester_id] ?? null,
                'user' => $this->person($friendship->requester),
            ])->values()->all(),
            'sentFriendRequests' => $user->sentFriendRequests()->with('addressee')->latest()->get()
                ->map(fn (Friendship $friendship) => [
                    'friendship_id' => $friendship->id,
                    'created_at' => $friendship->created_at?->toIso8601String(),
                    'user' => $this->person($friendship->addressee),
                ])->values()->all(),
            'following' => $outgoingFollows->where('status', FollowStatus::Accepted)
                ->sortBy(fn (Follow $follow) => mb_strtolower($follow->followed->name))
                ->map(fn (Follow $follow) => [
                    'follow_id' => $follow->id,
                    'since' => $follow->updated_at?->toIso8601String(),
                    'user' => $this->person($follow->followed),
                    ...$contactExtras($follow->followed),
                ])->values()->all(),
            'pendingFollowRequestsSent' => $outgoingFollows->where('status', FollowStatus::Pending)
                ->map(fn (Follow $follow) => [
                    'follow_id' => $follow->id,
                    'since' => $follow->created_at?->toIso8601String(),
                    'user' => $this->person($follow->followed),
                ])->values()->all(),
            'followers' => $followers
                ->sortBy(fn (Follow $follow) => mb_strtolower($follow->follower->name))
                ->map(fn (Follow $follow) => [
                    'follow_id' => $follow->id,
                    'since' => $follow->updated_at?->toIso8601String(),
                    'follows_back' => $followsBack->get($follow->follower_id),
                    'mutual_count' => $mutualCounts[$follow->follower_id] ?? null,
                    'user' => $this->person($follow->follower),
                ])->values()->all(),
            'pendingFollowerRequests' => $pendingFollowerRequests->map(fn (Follow $follow) => [
                'follow_id' => $follow->id,
                'since' => $follow->created_at?->toIso8601String(),
                'mutual_count' => $mutualCounts[$follow->follower_id] ?? null,
                'user' => $this->person($follow->follower),
            ])->values()->all(),
            'friendGroups' => $this->summarizeGroups($user->contactGroups()->ofType(ContactGroupType::Friend)->with('members')->get()),
            'followingGroups' => $this->summarizeGroups($user->contactGroups()->ofType(ContactGroupType::Following)->with('members')->get()),
            'categories' => Tag::categoriesTree(),
            'findQuery' => $findQuery,
            'findResults' => array_map(fn (array $result) => [
                ...$result,
                'mutual_count' => $mutualCounts[$result['id']] ?? null,
            ], $findResults),
        ]);
    }

    /**
     * @return array{id: int, name: string, username: string, avatar_url: string|null}
     */
    private function person(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, username: string, avatar_url: string|null, friend_status: string, friendship_id: int|null, follow_status: string, follow_id: int|null}>
     */
    private function findNewContacts(string $term, User $viewer): array
    {
        if ($term === '') {
            return [];
        }

        $results = User::query()
            ->whereKeyNot($viewer->getKey())
            ->where('searchable', true)
            ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'username', 'avatar_path']);

        $candidateIds = $results->pluck('id');

        $friendships = Friendship::query()
            ->where(fn ($query) => $query->where('requester_id', $viewer->getKey())->whereIn('addressee_id', $candidateIds))
            ->orWhere(fn ($query) => $query->where('addressee_id', $viewer->getKey())->whereIn('requester_id', $candidateIds))
            ->get();

        $follows = Follow::query()
            ->where('follower_id', $viewer->getKey())
            ->whereIn('followed_id', $candidateIds)
            ->get()
            ->keyBy('followed_id');

        return $results->map(function (User $candidate) use ($friendships, $follows, $viewer) {
            $friendship = $friendships->first(
                fn (Friendship $f) => $f->requester_id === $candidate->id || $f->addressee_id === $candidate->id
            );

            $friendStatus = $friendship?->statusFor($viewer) ?? 'none';

            $follow = $follows->get($candidate->id);
            $followStatus = match (true) {
                $follow === null => 'none',
                $follow->status === FollowStatus::Accepted => 'following',
                default => 'pending',
            };

            return [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'username' => $candidate->username,
                'avatar_url' => $candidate->avatar_url,
                'friend_status' => $friendStatus,
                'friendship_id' => $friendStatus === 'none' ? null : $friendship?->id,
                'follow_status' => $followStatus,
                'follow_id' => $follow?->id,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, ContactGroup>  $groups
     * @return array<int, array{id: int, name: string, members: array<int, array{id: int, name: string, username: string, avatar_url: string|null}>}>
     */
    private function summarizeGroups(Collection $groups): array
    {
        return $groups->map(fn (ContactGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'members' => $group->members->sortBy(fn (User $member) => mb_strtolower($member->name))
                ->map(fn (User $member) => $this->person($member))
                ->values()
                ->all(),
        ])->values()->all();
    }
}
