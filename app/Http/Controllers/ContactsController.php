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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContactsController extends Controller
{
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

        $findQuery = trim((string) $request->string('find'));

        return Inertia::render('contacts/Index', [
            'friends' => $this->summarizeUsers($user->friends(), $preferencesByContact, $mutedContactIds),
            'pendingFriendRequests' => $this->summarizeFriendships($user->pendingFriendRequests()->with('requester')->get(), 'requester'),
            'sentFriendRequests' => $this->summarizeFriendships($user->sentFriendRequests()->with('addressee')->get(), 'addressee'),
            'following' => $this->summarizeFollows(
                Follow::query()->where('follower_id', $user->id)->accepted()->with('followed')->get(),
                'followed',
                $preferencesByContact,
                $mutedContactIds
            ),
            'pendingFollowRequestsSent' => $this->summarizeFollows(
                Follow::query()->where('follower_id', $user->id)->pending()->with('followed')->get(),
                'followed'
            ),
            'followers' => $this->summarizeFollows(
                Follow::query()->where('followed_id', $user->id)->accepted()->with('follower')->get(),
                'follower'
            ),
            'pendingFollowerRequests' => $this->summarizeFollows(
                Follow::query()->where('followed_id', $user->id)->pending()->with('follower')->get(),
                'follower'
            ),
            'friendGroups' => $this->summarizeGroups($user->contactGroups()->ofType(ContactGroupType::Friend)->with('members')->get()),
            'followingGroups' => $this->summarizeGroups($user->contactGroups()->ofType(ContactGroupType::Following)->with('members')->get()),
            'categories' => $this->categoriesTree(),
            'findQuery' => $findQuery,
            'findResults' => $this->findNewContacts($findQuery, $user),
        ]);
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
     * @param  Collection<int, User>  $users
     * @param  Collection<int|string, array<int, int>>|null  $preferencesByContact
     * @param  Collection<int, int>|null  $mutedContactIds
     * @return array<int, array{id: int, name: string, username: string, avatar_url: string|null, tag_preference_ids?: array<int>, muted?: bool}>
     */
    private function summarizeUsers(Collection $users, ?Collection $preferencesByContact = null, ?Collection $mutedContactIds = null): array
    {
        return $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            ...($preferencesByContact === null ? [] : [
                'tag_preference_ids' => $preferencesByContact->get($user->id, []),
                'muted' => $mutedContactIds !== null && $mutedContactIds->contains($user->id),
            ]),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Friendship>  $friendships
     * @return array<int, array{friendship_id: int, user: array{id: int, name: string, username: string, avatar_url: string|null}}>
     */
    private function summarizeFriendships(Collection $friendships, string $relation): array
    {
        return $friendships->map(fn (Friendship $friendship) => [
            'friendship_id' => $friendship->id,
            'user' => [
                'id' => $friendship->{$relation}->id,
                'name' => $friendship->{$relation}->name,
                'username' => $friendship->{$relation}->username,
                'avatar_url' => $friendship->{$relation}->avatar_url,
            ],
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Follow>  $follows
     * @param  Collection<int|string, array<int, int>>|null  $preferencesByContact
     * @param  Collection<int, int>|null  $mutedContactIds
     * @return array<int, array{follow_id: int, user: array{id: int, name: string, username: string, avatar_url: string|null}, tag_preference_ids?: array<int>, muted?: bool}>
     */
    private function summarizeFollows(Collection $follows, string $relation, ?Collection $preferencesByContact = null, ?Collection $mutedContactIds = null): array
    {
        return $follows->map(fn (Follow $follow) => [
            'follow_id' => $follow->id,
            'user' => [
                'id' => $follow->{$relation}->id,
                'name' => $follow->{$relation}->name,
                'username' => $follow->{$relation}->username,
                'avatar_url' => $follow->{$relation}->avatar_url,
            ],
            ...($preferencesByContact === null ? [] : [
                'tag_preference_ids' => $preferencesByContact->get($follow->{$relation}->id, []),
                'muted' => $mutedContactIds !== null && $mutedContactIds->contains($follow->{$relation}->id),
            ]),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, ContactGroup>  $groups
     * @return array<int, array{id: int, name: string, members: array<int, array{id: int, name: string, username: string}>}>
     */
    private function summarizeGroups(Collection $groups): array
    {
        return $groups->map(fn (ContactGroup $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'members' => $this->summarizeUsers($group->members),
        ])->values()->all();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, children: array<int, array{id: int, name: string, slug: string}>}>
     */
    private function categoriesTree(): array
    {
        return Tag::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('position')])
            ->orderBy('position')
            ->get()
            ->map(fn (Tag $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => $category->children->map(fn (Tag $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])->values()->all(),
            ])->values()->all();
    }
}
